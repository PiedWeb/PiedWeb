/**
 * node packages/google/src/Puppeteer/launchBrowser.js &
PROXY_GATE=https://127.0.0.1:9876 \
CHROME_BIN=/usr/bin/google-chrome \
node packages/google/src/Puppeteer/launchBrowser.js 'fr' > ws.log 2>&1 &
PUPPETEER_HEADLESS=0 node packages/google/src/Puppeteer/launchBrowser.js 'fr'

PUPPETEER_LANG=en
PUPPETEER_WINDOW_SIZE=1920,1080
PUPPETEER_USER_DATA_DIR=...
PROXY_GATE=...

PUPPETEER_HEADLESS=0 node vendor/piedweb/google/src/Puppeteer/launchBrowser.js 'fr' '1920,1080' &
 */

const { launchBrowser } = require('./launchBrowserHelper');

launchBrowser().then(() => process.stdin.resume());
