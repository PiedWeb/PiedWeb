/**
 * node packages/google/src/Puppeteer/launchBrowser.js &
PROXY_GATE=https://127.0.0.1:9876 \
CHROME_BIN=/usr/bin/google-chrome \
node packages/google/src/Puppeteer/launchBrowser.js 'fr' > ws.log 2>&1 &
PUPPETEER_HEADLESS=0 node packages/google/src/Puppeteer/launchBrowser.js 'fr'

PUPPETEER_HEADLESS=0 node vendor/piedweb/google/src/Puppeteer/launchBrowser.js 'fr' '1920,1080' &
 */

const { executablePath, Browser } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

/**
 * @returns {Browser}
 */
async function launchBrowser() {
  const headless = ['false', '0'].includes(process.env.PUPPETEER_HEADLESS) ? false : true;
  const windowSize = process.argv[3] ? process.argv[3] : '';
  const proxy = process.env.PROXY_GATE ? process.env.PROXY_GATE : '';
  const userDataDir = process.env.PUPPETEER_USER_DATA_DIR || null;

  const options = {
    defaultViewport: null,
    headless: headless,
    executablePath: process.env.CHROME_BIN ?? '/usr/bin/google-chrome',
    ...(userDataDir && { userDataDir: userDataDir }),
    args: [
      ...[
        '--disable-web-security',
        '--lang=' + (process.argv[2] ?? 'en'),
        '--accept-lang=' + (process.argv[2] ?? 'en'),
        '--no-sandbox',
        '--disable-setuid-sandbox',
        // '--single-process',
        '--no-zygote',
      ],
      ...(proxy ? ['--proxy-server=' + proxy] : []),
      ...(windowSize ? ['--window-size=' + windowSize] : ['--window-size=360,840']),
    ],
  };

  /** @type {Browser} */
  browser = await puppeteer.launch(options);

  // Wait for the browser to launch and retrieve the WebSocket endpoint
  console.log(await browser.wsEndpoint());

  return browser;
}

launchBrowser().then(() => process.stdin.resume());
