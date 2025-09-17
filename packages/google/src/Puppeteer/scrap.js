/**
 *
 * PUPPETEER_WS_ENDPOINT='xxx' node packages/google/src/Puppeteer/scrap.js https://www.google.fr/search?q=pied+web
 * PUPPETEER_HEADLESS=0 PUPPETEER_WS_ENDPOINT='ws://127.0.0.1:37109/devtools/browser/a2943f64-8a79-488f-bac8-837b9b4f4ee2' node packages/google/src/Puppeteer/scrap.js https://www.google.fr/search?q=pied+web

 */
const { Page } = require('puppeteer');
const { connectBrowserPage } = require('./connectBrowserPage');

const url = process.argv[2];
const maxPages = process.argv[3] ? parseInt(process.argv[3], 10) : 5;
get(url, maxPages).then((source) => {
  console.log(source);
  process.exit(0);
});

/** @param {int} ms */
function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**  @param {Page} page */
async function manageCookie(page) {
  const selectors = [
    "::-p-xpath(//div[text()='Tout accepter']/ancestor::button)",
    'input[type="submit"][value="Tout accepter"]',
  ];

  for (let selector of selectors) {
    let cookieAcceptBtns = await page.$$(selector);
    for (let cookieAcceptBtn of cookieAcceptBtns) {
      if (cookieAcceptBtn) {
        await cookieAcceptBtn.evaluate((el) => el.scrollIntoView()); // bretelles
        await cookieAcceptBtn.scrollIntoView();
        if (cookieAcceptBtn.isVisible()) {
          await page.waitForSelector(selector, { visible: true, timeout: 2000 }); // et ceintures
          await sleep(350);
          await cookieAcceptBtn.tap(cookieAcceptBtn);
          await sleep(350);
        }
      }
    }
  }
}

/**
 * @param {Page} page
 * @param {int} maxPages
 */
async function manageLoadMoreResultsViaInfiniteScroll(page, maxPages) {
  let i = 1;
  while (true) {
    let scrollHeight = await page.evaluate(() => document.body.scrollHeight);
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await sleep(350);
    const isHeighten = await page.evaluate((scrollHeight) => {
      return document.body.scrollHeight > scrollHeight;
    }, scrollHeight);
    // limiter à maxPages pages
    if (!isHeighten || i >= maxPages) break;
    i++;
  }
}

/**
 * @param {Page} page
 * @param {int} maxPages
 */
async function manageLoadMoreResultsViaBtn(page, maxPages, clicked = 1) {
  if (clicked >= maxPages) return;
  let navigationBlock = await page.$('h1 ::-p-text(Page Navigation)');
  if (navigationBlock === null) return;
  await navigationBlock.scrollIntoView();
  await sleep(250);

  const moreBtnSelector = 'a[aria-label="Autres résultats de recherche"]';
  let moreBtn = await page.$(moreBtnSelector);
  if (null === moreBtn) return console.log('Pas de boutons `Autres résultats`');

  await moreBtn.evaluate((el) => el.scrollIntoView()); // bretelles
  await moreBtn.scrollIntoView(moreBtn);
  await sleep(750);
  if (!(await moreBtn.isVisible())) return;
  try {
    await page.waitForSelector(moreBtnSelector, { visible: true, timeout: 1000 }); // et ceinture
    await moreBtn.tap();
  } catch (error) {
    console.log(`moreBtn found but not able to click it`);
  }
  await sleep(500);
  clicked++;
  return await manageLoadMoreResultsViaBtn(page, maxPages, clicked);
}

/**  @param {string} url */
async function get(url, maxPages) {
  const page = await connectBrowserPage();
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  await sleep(1000);
  // if there is a captcha, close an restart the browser in headless = false
  await manageCookie(page);
  await manageLoadMoreResultsViaInfiniteScroll(page, maxPages);
  await manageLoadMoreResultsViaBtn(page, maxPages);
  return await page.content();
}
