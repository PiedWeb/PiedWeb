/**
 * node packages/google/src/Puppeteer/launchBrowser.js &
 * PROXY_GATE=https://127.0.0.1:9876 CHROME_BIN=/usr/bin/google-chrome node packages/google/src/Puppeteer/launchBrowser.js 'fr' > ws.log 2>&1 &
 */

const { executablePath, Browser } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

async function launchBrowser() {
  /** @type {Browser} */
  let browser = await puppeteer.launch({
    headless: false,
    executablePath: process.env.CHROME_BIN ?? '/usr/bin/google-chrome',
    args: [
      ...[
        '--disable-web-security',
        '--lang=' + (process.argv[2] ?? 'en'),
        '--accept-lang=' + (process.argv[2] ?? 'en'),
        '--no-sandbox',
        '--disable-setuid-sandbox',
        // --proxy-server=127.0.0.1:9876
      ],
      ...(process.env.PROXY_GATE ? ['--proxy-server=' + process.env.PROXY_GATE] : []),
    ],
  });

  // Wait for the browser to launch and retrieve the WebSocket endpoint
  console.log(await browser.wsEndpoint());
}

launchBrowser().then(() => process.stdin.resume());
