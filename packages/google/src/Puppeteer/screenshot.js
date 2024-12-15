/**
 * Return pixelPos
 * PUPPETEER_WS_ENDPOINT='xxx' node packages/google/src/Puppeteer/screenshot.js path/to/file.png
 */
const { connectBrowserPage } = require('./connectBrowserPage');

screenshot().then(() => {
  process.exit(0);
});

async function screenshot() {
  const page = await connectBrowserPage(false);
  const filePath = process.argv[2];
  page.screenshot({ fullPage: true, path: filePath });
}
