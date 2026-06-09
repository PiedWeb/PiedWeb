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
const { launchBrowser } = require('../src/Puppeteer/launchBrowserHelper');
const { emulate, emulateDesktop } = require('../src/Puppeteer/connectBrowserPage');

puppeteer.use(StealthPlugin());

// Row labels (substring, case-insensitive) that MUST be green on bot.sannysoft.com.
const CRITICAL = [/webdriver/i, /chrome/i, /permission/i, /plugins/i, /languages/i, /iframe/i, /notification/i];

function isCritical(label) {
  return CRITICAL.some((re) => re.test(label));
}

async function main() {
  const browser = await launchBrowser(true, null, null, '/tmp/fp_check_profile');
  const page = (await browser.pages())[0];
  await emulate(page); // prod identity

  let critical = [];

  // ---- 1. bot.sannysoft.com pass/fail table ----------------------------------------------
  await page.goto('https://bot.sannysoft.com/', { waitUntil: 'networkidle2', timeout: 45000 });
  await new Promise((r) => setTimeout(r, 2000));

  const rows = await page.evaluate(() => {
    const out = [];
    document.querySelectorAll('table tr').forEach((tr) => {
      const cells = [...tr.querySelectorAll('td')];
      if (cells.length < 2) return;
      const label = cells[0].innerText.trim();
      const failed = cells.some((c) => /(^|\s)(failed|warn)(\s|$)/i.test(c.className));
      const passed = cells.some((c) => /(^|\s)passed(\s|$)/i.test(c.className));
      if (label && (failed || passed)) out.push({ label, status: failed ? 'FAILED' : 'passed' });
    });
    return out;
  });

  console.log('\n=== bot.sannysoft.com ===');
  for (const r of rows) {
    const flag = r.status === 'FAILED' ? (isCritical(r.label) ? '  <-- CRITICAL' : '  (non-critical)') : '';
    console.log(`  [${r.status}] ${r.label}${flag}`);
    if (r.status === 'FAILED' && isCritical(r.label)) critical.push(r.label);
  }

  // ---- 2. in-page identity coherence -----------------------------------------------------
  const id = await page.evaluate(async () => {
    const high = await navigator.userAgentData?.getHighEntropyValues(['platform', 'model', 'uaFullVersion']).catch(() => ({}));
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
  if (uaSaysMobile && id.mobile !== true) critical.push('UA says mobile but userAgentData.mobile !== true');
  if (uaSaysMobile && /x86|win|mac/i.test(id.platform)) critical.push(`navigator.platform "${id.platform}" contradicts mobile UA`);
  if ((id.brands || []).length === 0) critical.push('userAgentData.brands is empty (client hints suppressed)');
  if (!/\[native code\]/.test(id.platformGetterToString)) critical.push('navigator.platform getter not masked as native code');
  // hardware must be plausible for the claimed device (deviceMemory is spec-capped at 8)
  if (uaSaysMobile && id.hardwareConcurrency > 16) critical.push(`hardwareConcurrency ${id.hardwareConcurrency} implausible for a mobile UA`);
  if (id.deviceMemory > 8) critical.push(`deviceMemory ${id.deviceMemory} exceeds the spec cap of 8 (host RAM leaking)`);

  // ---- 3. dump (no assert): GPU / audio + TLS --------------------------------------------
  const gpu = await page.evaluate(() => {
    try {
      const gl = document.createElement('canvas').getContext('webgl');
      const ext = gl.getExtension('WEBGL_debug_renderer_info');
      return { vendor: gl.getParameter(ext.UNMASKED_VENDOR_WEBGL), renderer: gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) };
    } catch (e) {
      return { error: String(e) };
    }
  });
  console.log('\n=== WebGL (dump only — software renderer = datacenter tell) ===');
  console.log(JSON.stringify(gpu, null, 2));

  try {
    await page.goto('https://tls.peet.ws/api/all', { waitUntil: 'domcontentloaded', timeout: 20000 });
    const tls = await page.evaluate(() => JSON.parse(document.body.innerText));
    console.log('\n=== TLS / HTTP2 (dump only — real Chrome here) ===');
    console.log(JSON.stringify({ ja3: tls.tls?.ja3, ja4: tls.tls?.ja4, http2: tls.http2?.akamai_fingerprint, ua: tls.http_version }, null, 2));
  } catch (e) {
    console.log('\n=== TLS dump skipped:', String(e.message || e), '===');
  }

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
      gpu = { vendor: gl.getParameter(ext.UNMASKED_VENDOR_WEBGL), renderer: gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) };
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
  if (/Mobile|Android/.test(desk.userAgent)) critical.push('emulateDesktop switched to a mobile UA (would change extracted content)');
  // headless/datacenter tells must be masked
  if (desk.deviceMemory > 8) critical.push(`emulateDesktop deviceMemory ${desk.deviceMemory} exceeds the spec cap of 8 (host RAM leaking)`);
  if (/swiftshader|llvmpipe|software/i.test(desk.gpu.renderer || '')) critical.push(`emulateDesktop WebGL renderer "${desk.gpu.renderer}" is still a software/datacenter tell`);
  if (desk.outerHeight && desk.innerHeight && desk.outerHeight < desk.innerHeight) critical.push('emulateDesktop outerHeight < innerHeight (headless window leak)');
  await deskPage.close();

  await browser.close();

  // ---- verdict ---------------------------------------------------------------------------
  console.log('\n=== VERDICT ===');
  if (critical.length > 0) {
    console.log('FAIL — critical automation tells:\n  - ' + critical.join('\n  - '));
    console.log('\n(Note: this only checks the JS surface. IP/ASN reputation and behavioral scoring are server-side and not covered here.)');
    process.exit(1);
  }
  console.log('PASS — JS surface clean. (IP/ASN reputation + behavioral scoring remain server-side, untested here.)');
  process.exit(0);
}

main().catch((e) => {
  console.error('fingerprint-check error:', e);
  process.exit(2);
});
