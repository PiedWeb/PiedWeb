/**
 * This file contain a module
 */

const { Browser, Page } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

/** @return {Promise<Page>} */
async function connectBrowserPage(
  mustEmulate = true,
  options = {},
  timezoneId = null,
  fpSeed = '',
) {
  const WsEndpoint = process.env.PUPPETEER_WS_ENDPOINT;
  /** @type {Browser} */
  const browser = await puppeteer.connect({ browserWSEndpoint: WsEndpoint, ...options });
  const pages = await browser.pages();
  if (!pages[0]) throw new Error('no page found');
  const page = pages[0];
  if (mustEmulate) await emulate(page, timezoneId, fpSeed);
  return page;
}

/**
 * @param {Page} page
 * @param {string|null} timezoneId IANA zone matching the target lane (e.g. 'Europe/Paris' for
 *   google.fr, 'America/New_York' for google.com); the caller derives it from the target URL.
 * @param {string} fpSeed per-egress seed (the proxy gate / 'direct') so battery + connection vary
 *   per IP instead of being a single value shared across the whole fleet, yet stay stable per IP.
 */
async function emulate(page, timezoneId = null, fpSeed = '') {
  const client = await page.createCDPSession();

  // Derive the real Chrome version so UA, brands and fullVersionList all agree with the
  // running binary (a UA claiming Chrome/139 on a Chrome/148 process is a detectable mismatch).
  const versionString = await page.browser().version(); // e.g. "HeadlessChrome/148.0.7778.178"
  const fullVersion = (versionString.match(/[\d][\d.]+/) || ['148.0.0.0'])[0];
  const major = fullVersion.split('.')[0];

  const userAgent =
    process.env.USER_AGENT ??
    `Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/${major}.0.0.0 Mobile Safari/537.36`;

  // Without userAgentMetadata, overriding userAgent wipes navigator.userAgentData to empty and
  // suppresses every Sec-CH-UA* header — Chrome never does that, so it flags as automation.
  // Supply metadata consistent with the mobile Android UA above.
  await client.send('Network.setUserAgentOverride', {
    userAgent,
    acceptLanguage: process.env.ACCEPT_LANGUAGE ?? undefined,
    userAgentMetadata: {
      brands: [
        { brand: 'Chromium', version: major },
        { brand: 'Google Chrome', version: major },
        { brand: 'Not=A?Brand', version: '24' },
      ],
      fullVersionList: [
        { brand: 'Chromium', version: fullVersion },
        { brand: 'Google Chrome', version: fullVersion },
        { brand: 'Not=A?Brand', version: '24.0.0.0' },
      ],
      fullVersion,
      platform: 'Android',
      platformVersion: '10.0.0',
      architecture: '',
      model: 'K',
      mobile: true,
      bitness: '',
      wow64: false,
    },
  });
  // extraFp gates the full real-device hardening (touch/plugins/screen/timezone/connection + the
  // 400x890 profile) so the whole bundle can be A/B'd via FP_EXTRA against the minimal-mobile
  // baseline. The WebGL string fix below stays ungated — the old value was simply wrong.
  const extraFp = !['0', 'false'].includes(process.env.FP_EXTRA || '');
  // Independent of extraFp: canvas/WebGL/audio pixel-hash poisoning is the riskiest lever, default OFF,
  // enabled only via FP_CANVAS=1 so its captcha effect can be A/B'd on its own (see the block below).
  const fpCanvas = ['1', 'true'].includes(String(process.env.FP_CANVAS || '').toLowerCase());

  // Timezone: an Android phone browsing google.com from the server's Europe/Paris clock is a
  // lane/locale contradiction — Intl/Date leak the host TZ. Override to match the Accept-Language the
  // browser was launched with. Zone comes from the caller (derived from the target URL); SCRAP_TIMEZONE
  // overrides for tests. Baseline arm keeps the leak so the A/B isolates it.
  const tz = process.env.SCRAP_TIMEZONE || timezoneId;
  if (extraFp && tz) {
    await client.send('Emulation.setTimezoneOverride', { timezoneId: tz }).catch(() => {});
  }

  // Reference profile from an amiunique capture of a genuine Adreno-750 Android phone: 400x890 @ DPR3,
  // inner height 773 (screen minus the URL bar). The baseline arm keeps the old 360x640.
  // NOTE: these are one real device — when rotating a per-IP profile pool, vary screen/GPU/connection
  // together so the fleet doesn't broadcast a single shared watermark.
  const screenW = 400;
  const screenH = 890;
  await page.setViewport({
    width: extraFp ? screenW : 360,
    height: extraFp ? 773 : 640,
    deviceScaleFactor: 3,
    isMobile: true,
    hasTouch: true,
    isLandscape: false,
  });

  // CDP's UA override leaves legacy/hardware navigator fields untouched, so they leak the real
  // host: platform "Linux x86_64", and the server's core/RAM counts — both impossible for the
  // Android phone the UA claims (deviceMemory is even spec-capped at 8, so the host's 32 is a
  // dead giveaway). Align them to a plausible mobile profile, masking each getter's toString so
  // it still reads as native code.
  await page.evaluateOnNewDocument(
    (cfg) => {
      const { extraFp, fpCanvas, screenW, screenH, fpSeed } = cfg;
      // Deterministic PRNG factory seeded from a string. Battery/connection use it seeded from the egress
      // (stable per IP, distinct across IPs — no fleet-wide constant to watermark, no per-load flicker);
      // the canvas/audio poisoning below re-seeds a fresh stream per read so repeat reads stay identical.
      const makeRng = (seedStr) => {
        const str = String(seedStr || 'seed');
        let h = 1779033703 ^ str.length;
        for (let i = 0; i < str.length; i++) {
          h = Math.imul(h ^ str.charCodeAt(i), 3432918353);
          h = (h << 13) | (h >>> 19);
        }
        let s = h >>> 0;
        return () => {
          s = (s + 0x6d2b79f5) | 0;
          let t = Math.imul(s ^ (s >>> 15), 1 | s);
          t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
          return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
      };
      const seededRandom = makeRng(fpSeed);
      const pick = (arr) => arr[Math.floor(seededRandom() * arr.length)];
      // mask a getter's toString chain so it reads as native code (defeats Proxy/defineProperty checks)
      const mask = (name, fn) => {
        Object.defineProperty(fn, 'toString', {
          value: () => `function get ${name}() { [native code] }`,
        });
        Object.defineProperty(fn.toString, 'toString', {
          value: () => 'function toString() { [native code] }',
        });
        return fn;
      };
      const nativeGetter = (name, value) => mask(name, () => value);
      // mask a plain method's toString so a patched function still reads as native code
      const nativeFn = (fn, name) => {
        Object.defineProperty(fn, 'toString', {
          value: () => `function ${name}() { [native code] }`,
        });
        Object.defineProperty(fn.toString, 'toString', {
          value: () => 'function toString() { [native code] }',
        });
        return fn;
      };

      Object.defineProperty(navigator, 'platform', {
        get: nativeGetter('platform', 'Linux armv8l'),
        configurable: true,
      });
      Object.defineProperty(navigator, 'hardwareConcurrency', {
        get: nativeGetter('hardwareConcurrency', 8),
        configurable: true,
      });
      Object.defineProperty(navigator, 'deviceMemory', {
        get: nativeGetter('deviceMemory', 8),
        configurable: true,
      });

      if (extraFp) {
        // navigator.languages leaks Accept-Language HEADER syntax: the launch flag feeds it the full
        // "en-US,en;q=0.9" string and the q-value survives into the JS array (["en-US","en;q=0.9"]) —
        // a value no real browser produces. Strip everything after ';' so it reads as clean lang tags.
        try {
          const cleanLangs = Object.freeze(navigator.languages.map((l) => l.split(';')[0]));
          Object.defineProperty(navigator, 'languages', {
            get: nativeGetter('languages', cleanLangs),
            configurable: true,
          });
        } catch (e) {
          /* navigator.languages unavailable → leave as-is */
        }

        // Real Android Chrome: 5 touch points, 0 plugins. The host leaks 1 touch point, and the stealth
        // plugin injects a desktop-shaped navigator.plugins — both impossible on the phone the UA claims.
        Object.defineProperty(navigator, 'maxTouchPoints', {
          get: nativeGetter('maxTouchPoints', 5),
          configurable: true,
        });
        try {
          // keep the real PluginArray type (returning [] would itself be a tell), just make it empty
          const empty = Object.create(
            typeof PluginArray !== 'undefined' ? PluginArray.prototype : Object.prototype,
          );
          Object.defineProperty(empty, 'length', { get: () => 0 });
          empty.item = () => null;
          empty.namedItem = () => null;
          empty.refresh = () => {};
          Object.defineProperty(navigator, 'plugins', {
            get: nativeGetter('plugins', empty),
            configurable: true,
          });
        } catch (e) {
          /* PluginArray unavailable → leave as-is */
        }

        // navigator.connection: real mobile Chrome exposes a cellular 4g NetworkInformation. Headless
        // reports desktop-shaped/absent values — a mobile UA with no cellular connection is a tell. rtt
        // and downlink are seeded per-egress (real quantization: rtt to 25ms, downlink to 25kbps steps).
        const rtt = pick([50, 100, 150, 200, 250, 300]);
        const downlink = Math.round((1 + seededRandom() * 4) * 40) / 40;
        try {
          const c = navigator.connection;
          if (c) {
            const defc = (k, v) =>
              Object.defineProperty(c, k, { get: nativeGetter(k, v), configurable: true });
            defc('effectiveType', '4g');
            defc('rtt', rtt);
            defc('downlink', downlink);
            defc('downlinkMax', 100);
            defc('saveData', false);
            defc('type', 'cellular');
          }
        } catch (e) {
          /* NetworkInformation unavailable → leave as-is */
        }

        // Battery: a real phone exposes navigator.getBattery(); headless does not. Seed level/charging
        // per-egress so it is a plausible, IP-stable value rather than absent or a fleet-wide constant.
        try {
          const charging = seededRandom() > 0.5;
          const level = Math.round((0.15 + seededRandom() * 0.8) * 100) / 100;
          const battery = {
            charging,
            chargingTime: charging ? pick([0, 1200, 2400, 3600]) : Infinity,
            dischargingTime: charging ? Infinity : pick([7200, 12600, 18000, 25200]),
            level,
            addEventListener() {},
            removeEventListener() {},
            dispatchEvent() {
              return false;
            },
            onchargingchange: null,
            onchargingtimechange: null,
            ondischargingtimechange: null,
            onlevelchange: null,
          };
          Object.defineProperty(navigator, 'getBattery', {
            value: nativeFn(function getBattery() {
              return Promise.resolve(battery);
            }, 'getBattery'),
            configurable: true,
            writable: true,
          });
        } catch (e) {
          /* getBattery locked down → leave as-is */
        }

        // mediaDevices.enumerateDevices: a real phone reports mic + camera + speaker (labels empty until
        // permission granted); headless returns an empty list — a phone with no A/V hardware is a tell.
        try {
          if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            const devices = [
              { deviceId: '', kind: 'audioinput', label: '', groupId: '' },
              { deviceId: '', kind: 'videoinput', label: '', groupId: '' },
              { deviceId: '', kind: 'audiooutput', label: '', groupId: '' },
            ];
            navigator.mediaDevices.enumerateDevices = nativeFn(function enumerateDevices() {
              return Promise.resolve(devices.map((d) => ({ ...d, toJSON: () => d })));
            }, 'enumerateDevices');
          }
        } catch (e) {
          /* mediaDevices unavailable → leave as-is */
        }

        // Sensors: a real phone grants accelerometer/gyroscope (the Generic Sensor API); headless denies
        // them. Report 'granted' for the motion sensors so the permission surface matches a phone. Other
        // permission names fall through to the original (which the stealth plugin already normalises).
        try {
          if (navigator.permissions && navigator.permissions.query) {
            const granted = ['accelerometer', 'gyroscope', 'magnetometer', 'ambient-light-sensor'];
            const origQuery = navigator.permissions.query.bind(navigator.permissions);
            navigator.permissions.query = nativeFn(function query(desc) {
              if (desc && granted.includes(desc.name)) {
                return Promise.resolve({
                  state: 'granted',
                  onchange: null,
                  addEventListener() {},
                  removeEventListener() {},
                  dispatchEvent: () => false,
                });
              }
              return origQuery(desc);
            }, 'query');
          }
        } catch (e) {
          /* permissions unavailable → leave as-is */
        }

        // screen: setViewport pins screen to the visible viewport (773); a real phone's screen is the
        // full panel (890). Pin screen + avail*/orientation to the device profile so they cohere.
        try {
          const dims = {
            width: screenW,
            height: screenH,
            availWidth: screenW,
            availHeight: screenH,
          };
          for (const k in dims) {
            Object.defineProperty(screen, k, { get: nativeGetter(k, dims[k]), configurable: true });
          }
          if (screen.orientation) {
            Object.defineProperty(screen.orientation, 'type', {
              get: nativeGetter('type', 'portrait-primary'),
              configurable: true,
            });
            Object.defineProperty(screen.orientation, 'angle', {
              get: nativeGetter('angle', 0),
              configurable: true,
            });
          }
        } catch (e) {
          /* screen locked down → leave as-is */
        }

        // pdfViewerEnabled: desktop Chrome ships a built-in PDF plugin (true); Android Chrome has none
        // (false). The host leaks the desktop `true` under our mobile UA + 0 plugins — a clean contradiction.
        Object.defineProperty(navigator, 'pdfViewerEnabled', {
          get: nativeGetter('pdfViewerEnabled', false),
          configurable: true,
        });

        // DeviceMotion/Orientation: a real phone exposes these event constructors + the window on-handlers
        // AND (above) grants the motion sensors. Headless has the sensor grant but neither the constructors
        // nor the handlers — a granted sensor whose event surface is absent is the contradiction. Add
        // native-masked constructors + on-handlers so the motion surface coheres with the permission grant.
        try {
          const defEvent = (name, extra) => {
            if (typeof window[name] !== 'undefined') return;
            const C = function (type, init) {
              const e = new Event(type, init);
              Object.setPrototypeOf(e, C.prototype);
              for (const k in extra) e[k] = init && init[k] !== undefined ? init[k] : extra[k];
              return e;
            };
            Object.setPrototypeOf(C.prototype, Event.prototype);
            Object.defineProperty(C, 'name', { value: name });
            Object.defineProperty(window, name, {
              value: nativeFn(C, name),
              configurable: true,
              writable: true,
            });
          };
          defEvent('DeviceMotionEvent', {
            acceleration: null,
            accelerationIncludingGravity: null,
            rotationRate: null,
            interval: 16,
          });
          defEvent('DeviceOrientationEvent', {
            alpha: null,
            beta: null,
            gamma: null,
            absolute: false,
          });
          for (const h of ['ondevicemotion', 'ondeviceorientation']) {
            if (h in window) continue;
            let handler = null;
            Object.defineProperty(window, h, {
              get: mask(h, () => handler),
              set: (v) => {
                handler = v;
              },
              configurable: true,
            });
          }
        } catch (e) {
          /* event surface locked down → leave as-is */
        }
      }

      // window outer dims. Headless reports innerHeight > outerHeight (impossible on a real window).
      // The real-profile arm reports the full panel height (outer == screen, inner < outer, like a
      // phone with a URL bar); the baseline arm mirrors the live inner dims to kill the leak (kept as a
      // live getter, not a document-start snapshot, so it tracks the laid-out viewport).
      Object.defineProperty(window, 'outerWidth', {
        get: mask('outerWidth', () => (extraFp ? screenW : window.innerWidth)),
        configurable: true,
      });
      Object.defineProperty(window, 'outerHeight', {
        get: mask('outerHeight', () => (extraFp ? screenH : window.innerHeight)),
        configurable: true,
      });

      // WebGL: report the real Adreno-750 profile across the WHOLE enumerable param set, not just the
      // unmasked vendor/renderer strings. Headless renders via SwiftShader, which leaks a desktop
      // capability profile (highp mediump, extra extensions, different MAX_* limits) — a detector reading
      // the full set sees "claims Adreno, measures SwiftShader". Values captured from a genuine Adreno-750.
      // RESIDUAL: the actual rendered pixels (webglData image hash) still come from SwiftShader; the
      // FP_CANVAS block below moves that hash off the SwiftShader cluster (per-IP-stable poisoning).
      const GL_STR = {
        37445: 'Google Inc. (Qualcomm)',
        37446: 'ANGLE (Qualcomm, Adreno (TM) 750, OpenGL ES 3.2)',
      };
      const GL_NUM = {
        3379: 8192, // MAX_TEXTURE_SIZE
        34076: 8192, // MAX_CUBE_MAP_TEXTURE_SIZE
        34024: 16384, // MAX_RENDERBUFFER_SIZE
        36347: 256, // MAX_VERTEX_UNIFORM_VECTORS
        36349: 256, // MAX_FRAGMENT_UNIFORM_VECTORS
        36348: 31, // MAX_VARYING_VECTORS
        35661: 48, // MAX_COMBINED_TEXTURE_IMAGE_UNITS
        34930: 16, // MAX_TEXTURE_IMAGE_UNITS
        35660: 16, // MAX_VERTEX_TEXTURE_IMAGE_UNITS
        34921: 16, // MAX_VERTEX_ATTRIBS
        34047: 16, // MAX_TEXTURE_MAX_ANISOTROPY_EXT
      };
      const GL_I32 = { 3386: [16384, 16384] }; // MAX_VIEWPORT_DIMS → Int32Array
      const GL_F32 = { 33902: [1, 8], 33901: [1, 1023] }; // ALIASED_LINE_WIDTH_RANGE / ALIASED_POINT_SIZE_RANGE → Float32Array
      // SwiftShader lists 4 extensions Adreno lacks and misses 1 it has — reconcile to the real 32.
      const EXT_DROP = [
        'EXT_frag_depth',
        'EXT_shader_texture_lod',
        'WEBGL_draw_buffers',
        'WEBGL_polygon_mode',
      ];
      const EXT_ADD = 'WEBGL_blend_func_extended';
      // Adreno fragment mediump/lowp float = 10-bit (mobile); SwiftShader reports 23 (highp everywhere),
      // a desktop tell. Keyed "shaderType:precisionType" → [precision, rangeMin, rangeMax].
      const PREC = {
        '35632:36337': [10, 15, 15], // FRAGMENT_SHADER MEDIUM_FLOAT
        '35632:36336': [10, 15, 15], // FRAGMENT_SHADER LOW_FLOAT
        '35632:36340': [0, 15, 15], // FRAGMENT_SHADER MEDIUM_INT
        '35632:36339': [0, 15, 15], // FRAGMENT_SHADER LOW_INT
      };
      for (const proto of [window.WebGLRenderingContext, window.WebGL2RenderingContext]) {
        if (!proto || !proto.prototype) continue;

        const origGetParam = proto.prototype.getParameter;
        proto.prototype.getParameter = nativeFn(function getParameter(p) {
          if (p in GL_STR) return GL_STR[p];
          if (p in GL_NUM) return GL_NUM[p];
          if (p in GL_I32) return new Int32Array(GL_I32[p]);
          if (p in GL_F32) return new Float32Array(GL_F32[p]);
          return origGetParam.apply(this, arguments);
        }, 'getParameter');

        const origGetExts = proto.prototype.getSupportedExtensions;
        proto.prototype.getSupportedExtensions = nativeFn(function getSupportedExtensions() {
          const out = (origGetExts.apply(this, arguments) || []).filter(
            (e) => !EXT_DROP.includes(e),
          );
          if (!out.includes(EXT_ADD)) out.push(EXT_ADD);
          return out;
        }, 'getSupportedExtensions');

        const origGetExt = proto.prototype.getExtension;
        proto.prototype.getExtension = nativeFn(function getExtension(name) {
          if (EXT_DROP.includes(name)) return null; // hidden → must not resolve
          if (name === EXT_ADD) return {}; // claimed → return an object, not null
          return origGetExt.apply(this, arguments);
        }, 'getExtension');

        const origPrec = proto.prototype.getShaderPrecisionFormat;
        proto.prototype.getShaderPrecisionFormat = nativeFn(function getShaderPrecisionFormat(
          shaderType,
          precisionType,
        ) {
          const r = origPrec.apply(this, arguments);
          const spec = PREC[shaderType + ':' + precisionType];
          if (r && spec) {
            try {
              Object.defineProperty(r, 'precision', { value: spec[0] });
              Object.defineProperty(r, 'rangeMin', { value: spec[1] });
              Object.defineProperty(r, 'rangeMax', { value: spec[2] });
            } catch (e) {
              /* read-only on this engine → leave the real value */
            }
          }
          return r;
        }, 'getShaderPrecisionFormat');
      }

      // Pixel/audio hashes are the last hard tell: the WebGL strings above claim Adreno, but the actual
      // canvas/WebGL rasters and the AudioContext render still come from the box's SwiftShader/audio stack
      // — a fleet-shared, known-software signature that also CONTRADICTS the Adreno strings. We can't
      // reproduce a real Adreno's exact pixels without the GPU, but we can leave that known cluster: apply
      // a sparse, imperceptible (±1 LSB / ~1e-6 gain) perturbation seeded from the egress IP. Re-seeded per
      // read from fpSeed+dims, so repeat reads are byte-identical (no farbling-instability tell) yet the
      // hash is unique-and-stable per IP — one device = one hash, exactly like a real phone. FP_CANVAS-gated
      // (independent of FP_EXTRA) because it is the riskiest lever and must be A/B'd on its own.
      if (fpCanvas) {
        const perturbBytes = (data, w, h, tag) => {
          const rng = makeRng('canvas:' + fpSeed + ':' + tag + ':' + w + 'x' + h);
          const px = Math.max(1, w * h);
          const n = Math.min(64, Math.max(4, (px / 900) | 0));
          for (let k = 0; k < n; k++) {
            const base = ((rng() * px) | 0) * 4;
            // Flip the alpha LSB: stored directly (not premultiplied away like an RGB nudge on a
            // transparent pixel), so it always changes the encoded output. Also nudge one RGB channel
            // (clamped to 1..254 so it never lands on a no-op) for opaque pixels / RGB-only hashers.
            data[base + 3] ^= 1;
            const ch = (rng() * 3) | 0;
            const v = data[base + ch] + (rng() < 0.5 ? -1 : 1);
            data[base + ch] = v < 1 ? 1 : v > 254 ? 254 : v;
          }
        };

        // getImageData returns a fresh ImageData per call; the deterministic reseed makes the perturbation
        // idempotent (same content → same hash) without mutating the source canvas.
        const origGID = CanvasRenderingContext2D.prototype.getImageData;
        CanvasRenderingContext2D.prototype.getImageData = nativeFn(function getImageData(
          x,
          y,
          w,
          h,
        ) {
          const img = origGID.apply(this, arguments);
          try {
            perturbBytes(img.data, img.width, img.height, '2d');
          } catch (e) {
            /* leave the honest bytes */
          }
          return img;
        }, 'getImageData');

        // toDataURL/toBlob encode from a perturbed offscreen COPY, so the visible canvas is never mutated
        // and a double-read stays identical. Any failure falls back to the honest encoder.
        const encodeFromCopy = (canvas, apply) => {
          const w = canvas.width;
          const h = canvas.height;
          const off = document.createElement('canvas');
          off.width = w;
          off.height = h;
          const octx = off.getContext('2d');
          octx.drawImage(canvas, 0, 0);
          // Read via the UNHOOKED getImageData: the hooked one already perturbs, and perturbing again
          // with the same seed re-applies the identical flips (alpha ^= 1 twice) and cancels them out.
          // One explicit perturb keeps toDataURL consistent with the hooked getImageData's own output.
          const img = origGID.apply(octx, [0, 0, w, h]);
          perturbBytes(img.data, w, h, '2d');
          octx.putImageData(img, 0, 0);
          return apply(off);
        };
        const origTDU = HTMLCanvasElement.prototype.toDataURL;
        HTMLCanvasElement.prototype.toDataURL = nativeFn(function toDataURL() {
          const args = arguments;
          try {
            return encodeFromCopy(this, (c) => origTDU.apply(c, args));
          } catch (e) {
            return origTDU.apply(this, args);
          }
        }, 'toDataURL');
        const origTB = HTMLCanvasElement.prototype.toBlob;
        HTMLCanvasElement.prototype.toBlob = nativeFn(function toBlob(cb) {
          const rest = Array.prototype.slice.call(arguments, 1);
          try {
            encodeFromCopy(this, (c) => origTB.apply(c, [cb].concat(rest)));
          } catch (e) {
            origTB.apply(this, arguments);
          }
        }, 'toBlob');

        // WebGL readPixels: perturb the caller's byte buffer after the real read (skip float reads).
        for (const proto of [window.WebGLRenderingContext, window.WebGL2RenderingContext]) {
          if (!proto || !proto.prototype) continue;
          const origRP = proto.prototype.readPixels;
          proto.prototype.readPixels = nativeFn(function readPixels(
            x,
            y,
            w,
            h,
            format,
            type,
            pixels,
          ) {
            origRP.apply(this, arguments);
            try {
              if (pixels && pixels.BYTES_PER_ELEMENT === 1) perturbBytes(pixels, w, h, 'gl');
            } catch (e) {
              /* leave the honest bytes */
            }
          }, 'readPixels');
        }

        // AudioContext: scale rendered samples by a per-IP seeded gain of ~1±1e-6 (inaudible, well within
        // float noise) so the summed AudioContext fingerprint leaves the shared value. WeakSet-guarded on
        // the returned array → applied once per buffer, so repeat reads are identical.
        try {
          const audioGain = 1 + (makeRng('audio:' + fpSeed)() - 0.5) * 2e-6;
          const audioDone = new WeakSet();
          const origGCD = AudioBuffer.prototype.getChannelData;
          AudioBuffer.prototype.getChannelData = nativeFn(function getChannelData(channel) {
            const d = origGCD.apply(this, arguments);
            if (!audioDone.has(d)) {
              for (let i = 0; i < d.length; i++) d[i] *= audioGain;
              audioDone.add(d);
            }
            return d;
          }, 'getChannelData');
        } catch (e) {
          /* AudioBuffer unavailable → leave as-is */
        }
      }
    },
    { extraFp, fpCanvas, screenW, screenH, fpSeed },
  );
}

/**
 * Content-safe headless hardening for the desktop CF-fetch path (connectBrowserPage(false)).
 *
 * Unlike {@see emulate}, this overrides NO content-affecting signal — no mobile UA, no viewport, no
 * navigator.platform — so the page that comes back is the same desktop HTML curl would have fetched
 * (the CF rescue must not switch the site to its mobile variant). It only masks the headless /
 * datacenter tells Cloudflare Turnstile reads: SwiftShader WebGL, the innerHeight > outerHeight
 * window leak, and the server's leaked core/RAM counts. UA and platform stay the honest desktop Linux.
 *
 * @param {Page} page
 */
async function emulateDesktop(page) {
  await page.evaluateOnNewDocument(() => {
    // mask a getter's toString chain so it reads as native code (defeats Proxy/defineProperty checks)
    const mask = (name, fn) => {
      Object.defineProperty(fn, 'toString', {
        value: () => `function get ${name}() { [native code] }`,
      });
      Object.defineProperty(fn.toString, 'toString', {
        value: () => 'function toString() { [native code] }',
      });
      return fn;
    };
    const nativeGetter = (name, value) => mask(name, () => value);

    // deviceMemory is spec-capped at 8, so the host's 32 is impossible; hardwareConcurrency leaks the
    // server's core count. Cap both to a common desktop profile (UA/platform stay the honest Linux desktop).
    Object.defineProperty(navigator, 'hardwareConcurrency', {
      get: nativeGetter('hardwareConcurrency', 8),
      configurable: true,
    });
    Object.defineProperty(navigator, 'deviceMemory', {
      get: nativeGetter('deviceMemory', 8),
      configurable: true,
    });

    // Headless reports innerHeight > outerHeight (impossible on a real window). Mirror outer onto inner.
    Object.defineProperty(window, 'outerWidth', {
      get: mask('outerWidth', () => window.innerWidth),
      configurable: true,
    });
    Object.defineProperty(window, 'outerHeight', {
      get: mask('outerHeight', () => window.innerHeight),
      configurable: true,
    });

    // Headless on a server has no GPU → WebGL renders via SwiftShader/Mesa, a known datacenter
    // signature. Report a plausible Intel-integrated desktop GPU, matching the GPU backend to the
    // PRESENTED platform (the stealth plugin normalises the UA to Windows; a Linux Mesa/OpenGL
    // string under a Win32 navigator.platform would be its own contradiction). 37445/37446 =
    // UNMASKED_VENDOR_WEBGL / UNMASKED_RENDERER_WEBGL.
    const GL = /Win/i.test(navigator.platform)
      ? {
          37445: 'Google Inc. (Intel)',
          37446: 'ANGLE (Intel, Intel(R) UHD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11)',
        }
      : {
          37445: 'Google Inc. (Intel)',
          37446: 'ANGLE (Intel, Mesa Intel(R) UHD Graphics (CML GT2), OpenGL 4.6 (Core Profile))',
        };
    for (const proto of [window.WebGLRenderingContext, window.WebGL2RenderingContext]) {
      if (!proto || !proto.prototype) continue;
      const orig = proto.prototype.getParameter;
      const patched = function getParameter(p) {
        return p in GL ? GL[p] : orig.apply(this, arguments);
      };
      Object.defineProperty(patched, 'toString', {
        value: () => 'function getParameter() { [native code] }',
      });
      Object.defineProperty(patched.toString, 'toString', {
        value: () => 'function toString() { [native code] }',
      });
      proto.prototype.getParameter = patched;
    }
  });
}

module.exports = { connectBrowserPage, emulate, emulateDesktop };
