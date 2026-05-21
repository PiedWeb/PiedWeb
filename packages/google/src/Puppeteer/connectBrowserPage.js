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

  // CDP's UA override leaves the legacy navigator.platform untouched ("Linux x86_64"), which
  // contradicts the Android UA/client hints above. Align it to a mobile ARM value, masking the
  // getter's toString so it still reads as native code.
  await page.evaluateOnNewDocument(() => {
    const getter = function platform() {
      return 'Linux armv8l';
    };
    Object.defineProperty(getter, 'toString', {
      value: () => 'function get platform() { [native code] }',
    });
    Object.defineProperty(getter.toString, 'toString', {
      value: () => 'function toString() { [native code] }',
    });
    Object.defineProperty(navigator, 'platform', { get: getter, configurable: true });
  });
}

module.exports = { connectBrowserPage, emulate };
