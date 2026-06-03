/**
 * Return pixelPos
 * PUPPETEER_WS_ENDPOINT='xxx' node packages/google/src/Puppeteer/pixelPos.js xpath
 */
const { connectBrowserPage } = require('./connectBrowserPage');

pixelPos()
  .then((pixelPos) => {
    console.log(pixelPos);
    process.exit(0);
  })
  .catch(() => {
    // Browser/WS endpoint unreachable (e.g. shared Chrome crashed under memory
    // pressure). Pixel position is a secondary datum — degrade to 0 instead of
    // letting an unhandled rejection abort the node process (Node 25 default),
    // which would leave stdout empty and crash the whole search:extract batch.
    console.log(0);
    process.exit(0);
  });

async function pixelPos() {
  const page = await connectBrowserPage(false);
  await page.evaluate(() => window.scrollTo({ top: 0 }));
  const xpath = process.argv[2];
  const element = await page.$("::-p-xpath('" + xpath + "')");
  if (!element) return 0;
  const boundingBox = await element.boundingBox();
  if (!boundingBox || !boundingBox.y) return 0;
  return parseInt(boundingBox.y, 10);
}
