/**
 * Fingerprint self-check for the stealth browser.
 *
 *   node packages/google/tests/fingerprint-check.js
 *   composer test:fingerprint   /   npm run test:fingerprint
 *
 * Launches the prod browser (launchBrowser) with the prod identity (emulate) and runs it
 * against public bot-detection pages, asserting the JS-surface signals stay clean:
 *   - navigator.webdriver, Chrome object, permissions, plugins, languages   (automation tells)
 *   - userAgentData / Sec-CH-UA consistency with the UA                      (client hints)
 *   - navigator.platform, hardwareConcurrency, deviceMemory, window dims     (env coherence)
 *   - .toString() native-code masking of patched getters                    (Proxy/defineProperty tells)
 *
 * It then DUMPS (does not assert) the parts a headless server box cannot win locally:
 *   - Canvas / WebGL vendor+renderer / AudioContext  (software renderer = datacenter signature)
 *   - JA3 / JA4 / HTTP2 fingerprint                  (genuine real-Chrome here; printed for record)
 *
 * Exit 0 = JS surface clean, 1 = a critical automation tell regressed. This proves fingerprint
 * CONSISTENCY; it cannot certify the IP/ASN reputation or behavioral verdict (server-side only).
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const { launchBrowser, getFontConfigFile } = require('../src/Puppeteer/launchBrowserHelper');
const { emulate, emulateDesktop } = require('../src/Puppeteer/connectBrowserPage');
const { timezoneForUrl } = require('../src/Puppeteer/scrap');

puppeteer.use(StealthPlugin());

// Pure-function unit check (no browser): the lane→timezone mapping the scraper derives from the URL.
// Runs first so a mapping regression fails fast without spinning up Chrome.
function checkTimezoneForUrl() {
  const cases = [
    ['https://www.google.fr/search?q=x', 'Europe/Paris'],
    ['https://www.google.be/search?q=x', 'Europe/Paris'],
    ['https://www.google.ch/search?q=x', 'Europe/Paris'],
    ['https://www.google.lu/search?q=x', 'Europe/Paris'],
    ['https://www.google.com/search?q=x', 'America/New_York'],
    ['https://www.google.de/search?q=x', 'America/New_York'], // non-FR TLD falls to the EN lane
    ['not a url', null],
  ];
  const fails = cases
    .filter(([u, want]) => timezoneForUrl(u) !== want)
    .map(([u, want]) => `${u} → expected ${want}, got ${timezoneForUrl(u)}`);
  console.log('\n=== timezoneForUrl (unit) ===');
  console.log(fails.length ? 'FAIL:\n  ' + fails.join('\n  ') : `pass (${cases.length} cases)`);
  return fails;
}

async function main() {
  const critical = checkTimezoneForUrl();

  const browser = await launchBrowser(true, null, null, '/tmp/fp_check_profile');
  const page = (await browser.pages())[0];
  // Exercise the timezone override on the EN lane — an Android phone on google.com must not carry the
  // box's Europe/Paris clock. SCRAP_TIMEZONE stands in for the lane the scraper derives from the URL.
  process.env.SCRAP_TIMEZONE = process.env.SCRAP_TIMEZONE || 'America/New_York';
  await emulate(page, process.env.SCRAP_TIMEZONE); // prod identity

  // ---- 0. network-free coherence probe (CI-authoritative) --------------------------------
  // Runs on a local viewport-meta page (no network; navigator/screen/WebGL/Intl all resolve).
  // A viewport meta is required to represent a real SERP: without it, mobile Chrome lays out at the
  // legacy 980px default and innerWidth/innerHeight would not reflect the device metrics.
  // Asserts the mobile-profile fixes: timezone override, navigator.connection, screen 400x890,
  // inner<outer window coherence, WebGL ANGLE/Adreno string format, and the Android font (Roboto).
  const probeHtml =
    '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>x</body></html>';
  await page.goto('data:text/html,' + encodeURIComponent(probeHtml));
  const probe = await page.evaluate(async () => {
    const battery = navigator.getBattery ? await navigator.getBattery().catch(() => null) : null;
    const accel = navigator.permissions
      ? await navigator.permissions
          .query({ name: 'accelerometer' })
          .then((p) => p.state)
          .catch(() => null)
      : null;
    let gl = {};
    try {
      const ctx = document.createElement('canvas').getContext('webgl');
      const ext = ctx.getExtension('WEBGL_debug_renderer_info');
      gl = {
        vendor: ctx.getParameter(ext.UNMASKED_VENDOR_WEBGL),
        renderer: ctx.getParameter(ext.UNMASKED_RENDERER_WEBGL),
      };
    } catch (e) {
      gl = { error: String(e) };
    }
    const c = navigator.connection || {};
    // WebGL depth: mobile Adreno reports fragment mediump float precision = 10 (SwiftShader = 23).
    let glMediumFloat = null;
    try {
      const g = document.createElement('canvas').getContext('webgl');
      const p = g.getShaderPrecisionFormat(g.FRAGMENT_SHADER, g.MEDIUM_FLOAT);
      glMediumFloat = p && p.precision;
    } catch (e) {
      /* no webgl */
    }
    // Font-enumeration signature via width detection: Roboto (Android) must be present, and the box's
    // Linux desktop fonts must be invisible when the Chrome-scoped Android font profile is active.
    const fontDetect = (() => {
      const base = ['monospace', 'sans-serif', 'serif'];
      const ctx = document.createElement('canvas').getContext('2d');
      const S = 'mmmmmmmmmmlli WwGg';
      const m = (f) => {
        ctx.font = '72px ' + f;
        return ctx.measureText(S).width;
      };
      const bw = {};
      base.forEach((b) => (bw[b] = m(b)));
      const det = (f) => base.some((b) => m("'" + f + "'," + b) !== bw[b]);
      return {
        roboto: det('Roboto'),
        linux: ['DejaVu Sans', 'Liberation Sans', 'Ubuntu', 'Cantarell'].filter(det),
      };
    })();
    // Baseline canvas 2D hash with poisoning OFF (this page runs the default emulate). The FP_CANVAS
    // arm below re-renders the identical primitive with poisoning on and asserts the hash moved.
    const canvas2dHash = (() => {
      const fnv = (s) => {
        let h = 2166136261 >>> 0;
        for (let i = 0; i < s.length; i++) {
          h ^= s.charCodeAt(i);
          h = Math.imul(h, 16777619);
        }
        return (h >>> 0).toString(16);
      };
      const cv = document.createElement('canvas');
      cv.width = 240;
      cv.height = 60;
      const cx = cv.getContext('2d');
      cx.textBaseline = 'top';
      cx.font = '14px Arial';
      cx.fillStyle = '#f60';
      cx.fillRect(10, 1, 62, 20);
      cx.fillStyle = '#069';
      cx.fillText('Cwm fjordbank glyphs vext quiz', 2, 15);
      return fnv(cv.toDataURL());
    })();
    return {
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      language: navigator.language,
      languages: navigator.languages,
      intlLocale: Intl.DateTimeFormat().resolvedOptions().locale,
      glMediumFloat,
      fontDetect,
      battery: battery ? { charging: battery.charging, level: battery.level } : null,
      accel,
      connection: {
        effectiveType: c.effectiveType,
        rtt: c.rtt,
        downlink: c.downlink,
        type: c.type,
      },
      screen: {
        width: screen.width,
        height: screen.height,
        availHeight: screen.availHeight,
        orientation: screen.orientation && screen.orientation.type,
      },
      window: { innerHeight: window.innerHeight, outerHeight: window.outerHeight },
      gl,
      robotoAvailable: document.fonts ? document.fonts.check('12px "Roboto"') : null,
      pdfViewerEnabled: navigator.pdfViewerEnabled,
      deviceMotionEvent: typeof window.DeviceMotionEvent,
      onDeviceMotionInWindow: 'ondevicemotion' in window,
      canvas2dHash,
    };
  });
  console.log('\n=== coherence probe (local viewport-meta page) ===');
  console.log(JSON.stringify(probe, null, 2));

  if (probe.timezone !== process.env.SCRAP_TIMEZONE)
    critical.push(
      `timezone "${probe.timezone}" != override "${process.env.SCRAP_TIMEZONE}" (host TZ leaking)`,
    );
  if (probe.connection.effectiveType !== '4g')
    critical.push(
      `navigator.connection.effectiveType "${probe.connection.effectiveType}" is not the mobile 4g profile`,
    );
  if ((probe.languages || []).some((l) => l.includes(';')))
    critical.push(
      `navigator.languages "${JSON.stringify(probe.languages)}" leaks Accept-Language q-values`,
    );
  if ((probe.intlLocale || '').split('-')[0] !== (probe.language || '').split('-')[0])
    critical.push(
      `Intl locale "${probe.intlLocale}" contradicts navigator.language "${probe.language}" (box locale leaking)`,
    );
  if (!/^ANGLE \(Qualcomm, Adreno/.test(probe.gl.renderer || ''))
    critical.push(`WebGL renderer "${probe.gl.renderer}" is not the real ANGLE/Adreno format`);
  if (/swiftshader|llvmpipe|mesa|software/i.test(probe.gl.renderer || ''))
    critical.push(
      `WebGL renderer "${probe.gl.renderer}" still exposes a software/datacenter string`,
    );
  if (probe.screen.width !== 400 || probe.screen.height !== 890)
    critical.push(
      `screen ${probe.screen.width}x${probe.screen.height} != the 400x890 device profile`,
    );
  if (probe.window.innerHeight > probe.window.outerHeight)
    critical.push(
      `innerHeight ${probe.window.innerHeight} > outerHeight ${probe.window.outerHeight} (headless window leak)`,
    );
  if (probe.robotoAvailable === false)
    critical.push(
      'Roboto missing on the box — canvas text renders with Linux fonts, clustering the "phone" with desktop (install fonts-roboto)',
    );
  if (probe.glMediumFloat !== 10)
    critical.push(
      `WebGL fragment mediump float precision ${probe.glMediumFloat} != 10 (mobile GPU); SwiftShader/desktop leaking`,
    );
  if (!probe.fontDetect.roboto)
    critical.push(
      'Roboto not detected via width probe — canvas text will not render with the Android font',
    );
  // Only assert "no Linux fonts" when the Android font profile is actually active (its dirs exist);
  // on a box with a different font layout getFontConfigFile() is null and this is skipped, not failed.
  if (getFontConfigFile() && probe.fontDetect.linux.length) {
    critical.push(
      `Linux desktop fonts still visible to Chrome (${probe.fontDetect.linux.join(', ')}) — font profile not masking them`,
    );
  }
  if (!probe.battery || typeof probe.battery.level !== 'number')
    critical.push(
      'navigator.getBattery() absent — a phone with no BatteryManager is a headless tell',
    );
  if (probe.accel !== 'granted')
    critical.push(
      `accelerometer permission "${probe.accel}" != granted (headless denies motion sensors a real phone grants)`,
    );
  if (probe.pdfViewerEnabled !== false)
    critical.push(
      `navigator.pdfViewerEnabled ${probe.pdfViewerEnabled} != false (Android Chrome has no built-in PDF plugin; desktop value leaking under the mobile UA)`,
    );
  if (probe.deviceMotionEvent !== 'function')
    critical.push(
      `DeviceMotionEvent is "${probe.deviceMotionEvent}" not a constructor — the motion sensors are granted above but the event surface is absent (contradiction)`,
    );
  if (probe.onDeviceMotionInWindow !== true)
    critical.push(
      'window.ondevicemotion absent — a phone that grants the accelerometer must expose the motion event handler',
    );

  // ---- 1. bot.sannysoft.com pass/fail table (best-effort — external site, may be down in CI) ----
  const rows = await page
    .goto('https://bot.sannysoft.com/', { waitUntil: 'networkidle2', timeout: 45000 })
    .then(() => new Promise((r) => setTimeout(r, 2000)))
    .then(() =>
      page.evaluate(() => {
        const out = [];
        document.querySelectorAll('table tr').forEach((tr) => {
          const cells = [...tr.querySelectorAll('td')];
          if (cells.length < 2) return;
          const label = cells[0].innerText.trim();
          const failed = cells.some((c) => /(^|\s)(failed|warn)(\s|$)/i.test(c.className));
          const passed = cells.some((c) => /(^|\s)passed(\s|$)/i.test(c.className));
          if (label && (failed || passed))
            out.push({ label, status: failed ? 'FAILED' : 'passed' });
        });
        return out;
      }),
    )
    .catch((e) => {
      console.log('\n=== bot.sannysoft.com skipped (network):', String(e.message || e), '===');
      return [];
    });

  // Dump only: sannysoft is desktop-oriented, so mobile-correct values (0 plugins) read as "failed"
  // there — the fatal verdict lives in the coherence + identity-coherence assertions instead.
  console.log(
    '\n=== bot.sannysoft.com (dump only — desktop-oriented; mobile 0-plugins reads as failed) ===',
  );
  for (const r of rows) {
    console.log(`  [${r.status}] ${r.label}`);
  }

  // ---- 2. in-page identity coherence -----------------------------------------------------
  const id = await page.evaluate(async () => {
    const high = await navigator.userAgentData
      ?.getHighEntropyValues(['platform', 'model', 'uaFullVersion'])
      .catch(() => ({}));
    const platDesc = Object.getOwnPropertyDescriptor(navigator, 'platform');
    return {
      userAgent: navigator.userAgent,
      webdriver: navigator.webdriver,
      platform: navigator.platform,
      platformGetterToString: platDesc?.get ? platDesc.get.toString() : '(none)',
      mobile: navigator.userAgentData?.mobile,
      brands: (navigator.userAgentData?.brands || []).map((b) => `${b.brand} ${b.version}`),
      hardwareConcurrency: navigator.hardwareConcurrency,
      deviceMemory: navigator.deviceMemory,
      outerHeight: window.outerHeight,
      innerHeight: window.innerHeight,
      high,
    };
  });

  console.log('\n=== identity coherence ===');
  console.log(JSON.stringify(id, null, 2));

  // hard assertions on coherence
  const uaSaysMobile = /Mobile|Android/.test(id.userAgent);
  if (id.webdriver === true) critical.push('navigator.webdriver === true');
  if (uaSaysMobile && id.mobile !== true)
    critical.push('UA says mobile but userAgentData.mobile !== true');
  if (uaSaysMobile && /x86|win|mac/i.test(id.platform))
    critical.push(`navigator.platform "${id.platform}" contradicts mobile UA`);
  if ((id.brands || []).length === 0)
    critical.push('userAgentData.brands is empty (client hints suppressed)');
  if (!/\[native code\]/.test(id.platformGetterToString))
    critical.push('navigator.platform getter not masked as native code');
  // hardware must be plausible for the claimed device (deviceMemory is spec-capped at 8)
  if (uaSaysMobile && id.hardwareConcurrency > 16)
    critical.push(`hardwareConcurrency ${id.hardwareConcurrency} implausible for a mobile UA`);
  if (id.deviceMemory > 8)
    critical.push(`deviceMemory ${id.deviceMemory} exceeds the spec cap of 8 (host RAM leaking)`);

  // Version coherence — the point that answers "does a Chrome update flow through automatically?".
  // UA/brands/Sec-CH-UA are derived at runtime from the running binary, so a Chrome auto-update SHOULD
  // propagate with no code change — but only while the version parse (`/[\d][\d.]+/`) still holds. Assert
  // the presented Chrome major equals the real binary's across the UA, the userAgentData brand, and the
  // high-entropy uaFullVersion, so a future version-string format change (or a broken override) fails
  // loudly here instead of silently shipping a garbled/stale version.
  const realMajor = ((await browser.version()).match(/\d+/) || ['0'])[0];
  const uaMajor = (id.userAgent.match(/Chrome\/(\d+)/) || [])[1];
  const brandMajor = (id.brands.find((b) => /Google Chrome/.test(b)) || '').split(' ').pop();
  const highMajor = (id.high && id.high.uaFullVersion ? id.high.uaFullVersion : '').split('.')[0];
  if (uaMajor !== realMajor)
    critical.push(
      `UA Chrome major ${uaMajor} != running binary ${realMajor} — version derivation broke; a Chrome update is not flowing into the presented UA`,
    );
  if (brandMajor !== realMajor)
    critical.push(
      `userAgentData Google Chrome brand ${brandMajor} != running binary ${realMajor} (Sec-CH-UA version drift)`,
    );
  if (highMajor !== realMajor)
    critical.push(
      `getHighEntropyValues uaFullVersion major ${highMajor} != running binary ${realMajor}`,
    );

  // ---- 3. dump (no assert): GPU / audio + TLS --------------------------------------------
  const gpu = await page.evaluate(() => {
    try {
      const gl = document.createElement('canvas').getContext('webgl');
      const ext = gl.getExtension('WEBGL_debug_renderer_info');
      return {
        vendor: gl.getParameter(ext.UNMASKED_VENDOR_WEBGL),
        renderer: gl.getParameter(ext.UNMASKED_RENDERER_WEBGL),
      };
    } catch (e) {
      return { error: String(e) };
    }
  });
  console.log('\n=== WebGL (dump only — software renderer = datacenter tell) ===');
  console.log(JSON.stringify(gpu, null, 2));

  try {
    await page.goto('https://tls.peet.ws/api/all', {
      waitUntil: 'domcontentloaded',
      timeout: 20000,
    });
    const tls = await page.evaluate(() => JSON.parse(document.body.innerText));
    console.log('\n=== TLS / HTTP2 (dump only — real Chrome here) ===');
    console.log(
      JSON.stringify(
        {
          ja3: tls.tls?.ja3,
          ja4: tls.tls?.ja4,
          http2: tls.http2?.akamai_fingerprint,
          ua: tls.http_version,
        },
        null,
        2,
      ),
    );
  } catch (e) {
    console.log('\n=== TLS dump skipped:', String(e.message || e), '===');
  }

  // ---- 3b. CreepJS (dump only — external scorer, async + flaky) --------------------------
  // Surfaces the "lies" CreepJS detects (UA-says-mobile-but-X-says-desktop) and its trust score.
  // Non-asserting on purpose: its DOM/scoring drifts and the hard verdict lives in the offline
  // coherence probe above. Printed so a fingerprint regression is visible in the CI log.
  try {
    await page.goto('https://abrahamjuliot.github.io/creepjs/', {
      waitUntil: 'networkidle2',
      timeout: 60000,
    });
    await new Promise((r) => setTimeout(r, 8000)); // CreepJS computes its score asynchronously
    const creep = await page.evaluate(() => {
      const text = document.body.innerText;
      const lies = [...document.querySelectorAll('.lies, .bold-fail')]
        .map((e) => e.textContent.trim())
        .filter(Boolean)
        .slice(0, 25);
      return {
        trustScore: (text.match(/([\d.]+)\s*%/) || [])[1] || null,
        liesCount: (text.match(/lies\s*\((\d+)\)/i) || [])[1] || null,
        lies,
      };
    });
    console.log('\n=== CreepJS (dump only — surfaces detected lies) ===');
    console.log(JSON.stringify(creep, null, 2));
  } catch (e) {
    console.log('\n=== CreepJS skipped (network):', String(e.message || e), '===');
  }

  // ---- 3c. FP_CANVAS poisoning arm (offline, deterministic) ------------------------------
  // Re-render the SAME primitive as the baseline probe, now with FP_CANVAS on and a fixed seed. Asserts
  // the poisoned hash (a) stays byte-identical across repeat reads (a per-read-random poison would itself
  // be a spoofer tell) and (b) has moved off the SwiftShader baseline captured above.
  const fpcPage = await browser.newPage();
  const prevCanvasFlag = process.env.FP_CANVAS;
  process.env.FP_CANVAS = '1';
  await emulate(fpcPage, process.env.SCRAP_TIMEZONE, 'fp-selfcheck-seed');
  process.env.FP_CANVAS = prevCanvasFlag === undefined ? '' : prevCanvasFlag; // restore; nothing after depends on it
  await fpcPage.goto('data:text/html,' + encodeURIComponent(probeHtml));
  const fpc = await fpcPage.evaluate(() => {
    const fnv = (s) => {
      let h = 2166136261 >>> 0;
      for (let i = 0; i < s.length; i++) {
        h ^= s.charCodeAt(i);
        h = Math.imul(h, 16777619);
      }
      return (h >>> 0).toString(16);
    };
    const render = () => {
      const cv = document.createElement('canvas');
      cv.width = 240;
      cv.height = 60;
      const cx = cv.getContext('2d');
      cx.textBaseline = 'top';
      cx.font = '14px Arial';
      cx.fillStyle = '#f60';
      cx.fillRect(10, 1, 62, 20);
      cx.fillStyle = '#069';
      cx.fillText('Cwm fjordbank glyphs vext quiz', 2, 15);
      return fnv(cv.toDataURL());
    };
    return { a: render(), b: render() };
  });
  await fpcPage.close();
  console.log('\n=== FP_CANVAS poisoning arm ===');
  console.log(
    JSON.stringify(
      { baseline: probe.canvas2dHash, poisoned: fpc.a, stableAcrossReads: fpc.a === fpc.b },
      null,
      2,
    ),
  );
  if (fpc.a !== fpc.b)
    critical.push(
      `FP_CANVAS canvas hash unstable across reads (${fpc.a} != ${fpc.b}) — a per-read-random poison is itself a spoofer tell`,
    );
  if (fpc.a === probe.canvas2dHash)
    critical.push(
      'FP_CANVAS on did not move the canvas hash off the SwiftShader baseline (poisoning not applied)',
    );

  // ---- 4. desktop CF-fetch path (emulateDesktop) — content-safe headless hardening -------
  // The Cloudflare rescue uses connectBrowserPage(false)+emulateDesktop: it must mask the
  // headless/datacenter tells WITHOUT switching the page to its mobile variant (that would change
  // the extracted HTML). Probe on about:blank — no network needed, WebGL/navigator still resolve.
  const deskPage = await browser.newPage();
  await emulateDesktop(deskPage);
  await deskPage.goto('about:blank');
  const desk = await deskPage.evaluate(() => {
    let gpu = {};
    try {
      const gl = document.createElement('canvas').getContext('webgl');
      const ext = gl.getExtension('WEBGL_debug_renderer_info');
      gpu = {
        vendor: gl.getParameter(ext.UNMASKED_VENDOR_WEBGL),
        renderer: gl.getParameter(ext.UNMASKED_RENDERER_WEBGL),
      };
    } catch (e) {
      gpu = { error: String(e) };
    }
    return {
      userAgent: navigator.userAgent,
      platform: navigator.platform,
      deviceMemory: navigator.deviceMemory,
      hardwareConcurrency: navigator.hardwareConcurrency,
      outerHeight: window.outerHeight,
      innerHeight: window.innerHeight,
      gpu,
    };
  });
  console.log('\n=== emulateDesktop (CF-fetch path) ===');
  console.log(JSON.stringify(desk, null, 2));

  // content-safe: must stay DESKTOP (no mobile UA/platform switch — that changes page content)
  if (/Mobile|Android/.test(desk.userAgent))
    critical.push('emulateDesktop switched to a mobile UA (would change extracted content)');
  // headless/datacenter tells must be masked
  if (desk.deviceMemory > 8)
    critical.push(
      `emulateDesktop deviceMemory ${desk.deviceMemory} exceeds the spec cap of 8 (host RAM leaking)`,
    );
  if (/swiftshader|llvmpipe|software/i.test(desk.gpu.renderer || ''))
    critical.push(
      `emulateDesktop WebGL renderer "${desk.gpu.renderer}" is still a software/datacenter tell`,
    );
  if (desk.outerHeight && desk.innerHeight && desk.outerHeight < desk.innerHeight)
    critical.push('emulateDesktop outerHeight < innerHeight (headless window leak)');
  await deskPage.close();

  await browser.close();

  // ---- verdict ---------------------------------------------------------------------------
  console.log('\n=== VERDICT ===');
  if (critical.length > 0) {
    console.log('FAIL — critical automation tells:\n  - ' + critical.join('\n  - '));
    console.log(
      '\n(Note: this only checks the JS surface. IP/ASN reputation and behavioral scoring are server-side and not covered here.)',
    );
    process.exit(1);
  }
  console.log(
    'PASS — JS surface clean. (IP/ASN reputation + behavioral scoring remain server-side, untested here.)',
  );
  process.exit(0);
}

main().catch((e) => {
  console.error('fingerprint-check error:', e);
  process.exit(2);
});
