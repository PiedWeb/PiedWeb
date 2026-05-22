const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const WsEndpoint = process.env.PUPPETEER_WS_ENDPOINT;

// Hard cap: never outlive the caller if Chrome is stuck / port is half-open
setTimeout(() => process.exit(0), 8000);

closeBrowser().then(() => process.exit(0));

async function closeBrowser() {
  try {
    // Connect directly — skip emulate() so no CDP calls hang on a dead browser
    const browser = await puppeteer.connect({ browserWSEndpoint: WsEndpoint });
    await browser.close();
  } catch (error) {
    // ignore: ECONNREFUSED, detached frame, etc.
  }
}
