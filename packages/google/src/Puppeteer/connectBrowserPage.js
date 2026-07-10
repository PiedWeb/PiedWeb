/**
 * This file contain a module
 */

const { Browser, Page } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

/** @return {Promise<Page>} */
async function connectBrowserPage(mustEmulate = true, options = {}, timezoneId = null) {
  const WsEndpoint = process.env.PUPPETEER_WS_ENDPOINT;
  /** @type {Browser} */
  const browser = await puppeteer.connect({ browserWSEndpoint: WsEndpoint, ...options });
  const pages = await browser.pages();
  if (!pages[0]) throw new Error('no page found');
  const page = pages[0];
  if (mustEmulate) await emulate(page, timezoneId);
  return page;
}

/**
 * @param {Page} page
 * @param {string|null} timezoneId IANA zone matching the target lane (e.g. 'Europe/Paris' for
 *   google.fr, 'America/New_York' for google.com); the caller derives it from the target URL.
 */
async function emulate(page, timezoneId = null) {
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
  await page.evaluateOnNewDocument((cfg) => {
    const { extraFp, screenW, screenH } = cfg;
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

    Object.defineProperty(navigator, 'platform', { get: nativeGetter('platform', 'Linux armv8l'), configurable: true });
    Object.defineProperty(navigator, 'hardwareConcurrency', { get: nativeGetter('hardwareConcurrency', 8), configurable: true });
    Object.defineProperty(navigator, 'deviceMemory', { get: nativeGetter('deviceMemory', 8), configurable: true });

    if (extraFp) {
      // navigator.languages leaks Accept-Language HEADER syntax: the launch flag feeds it the full
      // "en-US,en;q=0.9" string and the q-value survives into the JS array (["en-US","en;q=0.9"]) —
      // a value no real browser produces. Strip everything after ';' so it reads as clean lang tags.
      try {
        const cleanLangs = Object.freeze(navigator.languages.map((l) => l.split(';')[0]));
        Object.defineProperty(navigator, 'languages', { get: nativeGetter('languages', cleanLangs), configurable: true });
      } catch (e) {
        /* navigator.languages unavailable → leave as-is */
      }

      // Real Android Chrome: 5 touch points, 0 plugins. The host leaks 1 touch point, and the stealth
      // plugin injects a desktop-shaped navigator.plugins — both impossible on the phone the UA claims.
      Object.defineProperty(navigator, 'maxTouchPoints', { get: nativeGetter('maxTouchPoints', 5), configurable: true });
      try {
        // keep the real PluginArray type (returning [] would itself be a tell), just make it empty
        const empty = Object.create(typeof PluginArray !== 'undefined' ? PluginArray.prototype : Object.prototype);
        Object.defineProperty(empty, 'length', { get: () => 0 });
        empty.item = () => null;
        empty.namedItem = () => null;
        empty.refresh = () => {};
        Object.defineProperty(navigator, 'plugins', { get: nativeGetter('plugins', empty), configurable: true });
      } catch (e) {
        /* PluginArray unavailable → leave as-is */
      }

      // navigator.connection: real mobile Chrome exposes a cellular 4g NetworkInformation. Headless
      // reports desktop-shaped/absent values — a mobile UA with no cellular connection is a tell.
      // (Fixed values; vary per-IP when rotating profiles so downlink/rtt aren't a fleet watermark.)
      try {
        const c = navigator.connection;
        if (c) {
          const defc = (k, v) => Object.defineProperty(c, k, { get: nativeGetter(k, v), configurable: true });
          defc('effectiveType', '4g');
          defc('rtt', 150);
          defc('downlink', 1.6);
          defc('downlinkMax', 100);
          defc('saveData', false);
          defc('type', 'cellular');
        }
      } catch (e) {
        /* NetworkInformation unavailable → leave as-is */
      }

      // screen: setViewport pins screen to the visible viewport (773); a real phone's screen is the
      // full panel (890). Pin screen + avail*/orientation to the device profile so they cohere.
      try {
        const dims = { width: screenW, height: screenH, availWidth: screenW, availHeight: screenH };
        for (const k in dims) {
          Object.defineProperty(screen, k, { get: nativeGetter(k, dims[k]), configurable: true });
        }
        if (screen.orientation) {
          Object.defineProperty(screen.orientation, 'type', { get: nativeGetter('type', 'portrait-primary'), configurable: true });
          Object.defineProperty(screen.orientation, 'angle', { get: nativeGetter('angle', 0), configurable: true });
        }
      } catch (e) {
        /* screen locked down → leave as-is */
      }
    }

    // window outer dims. Headless reports innerHeight > outerHeight (impossible on a real window).
    // The real-profile arm reports the full panel height (outer == screen, inner < outer, like a
    // phone with a URL bar); the baseline arm mirrors the live inner dims to kill the leak (kept as a
    // live getter, not a document-start snapshot, so it tracks the laid-out viewport).
    Object.defineProperty(window, 'outerWidth', { get: mask('outerWidth', () => (extraFp ? screenW : window.innerWidth)), configurable: true });
    Object.defineProperty(window, 'outerHeight', { get: mask('outerHeight', () => (extraFp ? screenH : window.innerHeight)), configurable: true });

    // Headless on a server has no GPU → WebGL renders via SwiftShader/Mesa, a known datacenter
    // signature. Report the exact real-device unmasked vendor/renderer strings. Real Chrome ALWAYS
    // wraps these in the ANGLE grammar; the old bare "Adreno (TM) 640" was a string no real Chrome
    // emits (format tell). CAVEAT: only these two strings are spoofed — the extension list,
    // MAX_TEXTURE_SIZE and shader-precision formats still come from the host's SwiftShader/Mesa, so a
    // detector reading the full param set sees an Adreno claim over a SwiftShader capability profile.
    const GL = { 37445: 'Google Inc. (Qualcomm)', 37446: 'ANGLE (Qualcomm, Adreno (TM) 750, OpenGL ES 3.2)' };
    for (const proto of [window.WebGLRenderingContext, window.WebGL2RenderingContext]) {
      if (!proto || !proto.prototype) continue;
      const orig = proto.prototype.getParameter;
      const patched = function getParameter(p) {
        return p in GL ? GL[p] : orig.apply(this, arguments);
      };
      Object.defineProperty(patched, 'toString', { value: () => 'function getParameter() { [native code] }' });
      Object.defineProperty(patched.toString, 'toString', { value: () => 'function toString() { [native code] }' });
      proto.prototype.getParameter = patched;
    }
  }, { extraFp, screenW, screenH });
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
    Object.defineProperty(navigator, 'hardwareConcurrency', { get: nativeGetter('hardwareConcurrency', 8), configurable: true });
    Object.defineProperty(navigator, 'deviceMemory', { get: nativeGetter('deviceMemory', 8), configurable: true });

    // Headless reports innerHeight > outerHeight (impossible on a real window). Mirror outer onto inner.
    Object.defineProperty(window, 'outerWidth', { get: mask('outerWidth', () => window.innerWidth), configurable: true });
    Object.defineProperty(window, 'outerHeight', { get: mask('outerHeight', () => window.innerHeight), configurable: true });

    // Headless on a server has no GPU → WebGL renders via SwiftShader/Mesa, a known datacenter
    // signature. Report a plausible Intel-integrated desktop GPU, matching the GPU backend to the
    // PRESENTED platform (the stealth plugin normalises the UA to Windows; a Linux Mesa/OpenGL
    // string under a Win32 navigator.platform would be its own contradiction). 37445/37446 =
    // UNMASKED_VENDOR_WEBGL / UNMASKED_RENDERER_WEBGL.
    const GL = /Win/i.test(navigator.platform)
      ? { 37445: 'Google Inc. (Intel)', 37446: 'ANGLE (Intel, Intel(R) UHD Graphics 630 Direct3D11 vs_5_0 ps_5_0, D3D11)' }
      : { 37445: 'Google Inc. (Intel)', 37446: 'ANGLE (Intel, Mesa Intel(R) UHD Graphics (CML GT2), OpenGL 4.6 (Core Profile))' };
    for (const proto of [window.WebGLRenderingContext, window.WebGL2RenderingContext]) {
      if (!proto || !proto.prototype) continue;
      const orig = proto.prototype.getParameter;
      const patched = function getParameter(p) {
        return p in GL ? GL[p] : orig.apply(this, arguments);
      };
      Object.defineProperty(patched, 'toString', { value: () => 'function getParameter() { [native code] }' });
      Object.defineProperty(patched.toString, 'toString', { value: () => 'function toString() { [native code] }' });
      proto.prototype.getParameter = patched;
    }
  });
}

module.exports = { connectBrowserPage, emulate, emulateDesktop };
