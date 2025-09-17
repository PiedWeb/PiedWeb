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
  await client.send('Network.setUserAgentOverride', {
    userAgent:
      process.env.USER_AGENT ??
      'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/139.0.0.0 Mobile Safari/537.36',
  });
  await page.setViewport({
    width: 360,
    height: 640,
    deviceScaleFactor: 3,
    isMobile: true,
    hasTouch: true,
    isLandscape: false,
  });
}

module.exports = { connectBrowserPage, emulate };
