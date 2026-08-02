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
  const xpath = process.argv[2];
  const element = await page.$("::-p-xpath('" + xpath + "')");
  if (!element) return 0;

  // Measure against the document, not the viewport. boundingBox() is viewport-relative, so this
  // used to scrollTo({ top: 0 }) first and then read the box — which only holds if the scroll has
  // actually settled by the time the read lands, and nothing here waits for that. scrap.js's
  // pagination loop leaves the page scrolled to the bottom, so whenever the reset had not taken
  // effect the box came back as documentY minus a still-large scroll offset: a large negative for
  // the top results, which aborted the whole search:extract run (~14 SERPs/month, each already paid
  // for in proxy/captcha). rect.top + scrollY does not depend on where the page happens to be
  // scrolled, so there is no reset to issue and no race to lose — whatever moves the scroll
  // (Google's own JS, scroll anchoring during continuous scroll, another tab) can no longer skew
  // the reading. It also stops perturbing the scroll position scrap.js set up.
  return await element.evaluate((el) => {
    const rect = el.getBoundingClientRect();
    // boundingBox() answers null for an element with no box (hidden/detached); keep that contract
    // rather than reporting the bare scroll offset for a rect that is all zeroes.
    if (rect.width === 0 && rect.height === 0) return 0;

    return Math.round(rect.top + window.scrollY);
  });
}
