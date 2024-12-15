/**
 * This file contain a module
 */

const { Browser, Page } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

/** @return {Promise<Page>} */
async function connectBrowserPage(mustEmulate = true) {
  const WsEndpoint = process.env.PUPPETEER_WS_ENDPOINT;
  /** @type {Browser} */
  const browser = await puppeteer.connect({ browserWSEndpoint: WsEndpoint });
  const pages = await browser.pages();
  if (!pages[0]) throw new Error('no page found');
  const page = pages[0];
  if (mustEmulate) await emulate(page);
  return page;
}

/**  @param {Page} page */
async function emulate(page) {
  await page.setUserAgent(
    process.env.USER_AGENT ??
      'Mozilla/5.0 (Linux; Android 12; SM-G973U1 Build/SP1A.210812.016; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.6778.39 Mobile Safari/537.36'
  );
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
