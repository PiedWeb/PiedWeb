const { Page, Browser } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const { connectBrowserPage } = require('./connectBrowserPage');
puppeteer.use(StealthPlugin());

closeBrowser().then(() => process.exit(0));

async function closeBrowser() {
  try {
    const page = await connectBrowserPage();
    await page.browser().close();
  } catch (error) {
    console.error(error);
  }
}
