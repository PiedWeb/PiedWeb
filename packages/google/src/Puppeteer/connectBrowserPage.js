/**
 * This file contain a module
 */

const { Browser, Page } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

/** @return {Promise<Page>} */
async function connectBrowserPage(mustEmulate = true, options = {}) {
  const WsEndpoint = process.env.PUPPETEER_WS_ENDPOINT;
  /** @type {Browser} */
  const browser = await puppeteer.connect({ browserWSEndpoint: WsEndpoint, ...options });
  const pages = await browser.pages();
  if (!pages[0]) throw new Error('no page found');
  const page = pages[0];
  if (mustEmulate) await emulate(page);
  return page;
}

/**  @param {Page} page */
async function emulate(page) {
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
  await page.setViewport({
    width: 360,
    height: 640,
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
  // extraFp gates the two newest overrides (maxTouchPoints/plugins) so they can be A/B'd via FP_EXTRA.
  const extraFp = !['0', 'false'].includes(process.env.FP_EXTRA || '');
  await page.evaluateOnNewDocument((extraFp) => {
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
    }

    // Headless reports innerHeight > outerHeight (impossible on a real window). Mobile Chrome runs
    // effectively fullscreen, so mirror outer onto inner to keep the relationship coherent.
    Object.defineProperty(window, 'outerWidth', { get: mask('outerWidth', () => window.innerWidth), configurable: true });
    Object.defineProperty(window, 'outerHeight', { get: mask('outerHeight', () => window.innerHeight), configurable: true });

    // Headless on a server has no GPU → WebGL renders via SwiftShader/Mesa, a known datacenter
    // signature. Report a plausible mobile GPU for the unmasked vendor/renderer params.
    const GL = { 37445: 'Qualcomm', 37446: 'Adreno (TM) 640' }; // UNMASKED_VENDOR / UNMASKED_RENDERER
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
  }, extraFp);
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
