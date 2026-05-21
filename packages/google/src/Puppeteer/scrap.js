/**
 *
 * PUPPETEER_WS_ENDPOINT='xxx' node packages/google/src/Puppeteer/scrap.js https://www.google.fr/search?q=pied+web
 * export PUPPETEER_HEADLESS=0
 * export PUPPETEER_WS_ENDPOINT='ws://127.0.0.1:41879/devtools/browser/c549a5c4-f341-4600-8e3e-1c0a9c39c44d'
 * export PUPPETEER_2CAPTCHA_TOKEN='XXX'
 * node packages/google/src/Puppeteer/scrap.js https://www.google.fr/search?q=pied+web

 */
const { Page } = require('puppeteer');
const { connectBrowserPage } = require('./connectBrowserPage');
const { movePointerHuman } = require('./human');
const RecaptchaPlugin = require('puppeteer-extra-plugin-recaptcha');
const puppeteer = require('puppeteer-extra');

const captchaToken = process.env.PUPPETEER_2CAPTCHA_TOKEN;

puppeteer.use(
  RecaptchaPlugin(
    captchaToken
      ? {
          provider: {
            id: '2captcha',
            token: captchaToken,
          },
          visualFeedback: true,
        }
      : {},
  ),
);

const url = process.argv[2];
const maxPages = process.argv[3] ? parseInt(process.argv[3], 10) : 5;
get(url, maxPages)
  .then((source) => {
    console.log(source);
    process.exit(0);
  })
  .catch((error) => {
    console.error('Error in launchBrowser.js:', error);
    process.exit(1);
  });

/** @param {int} ms */
function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * page.evaluate() can throw when the frame detaches mid-call — e.g. Google's infinite scroll
 * navigates to #ip=1 which destroys the execution context. Retry a few times before giving up.
 * Returns null on persistent failure so callers can stop cleanly with partial results.
 * @param {Page} page
 */
async function safeEvaluate(page, fn, ...args) {
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      return await page.evaluate(fn, ...args);
    } catch (e) {
      const msg = String((e && e.message) || e);
      if (!/detached frame|Execution context|context was destroyed|Target closed/i.test(msg)) {
        throw e;
      }
      await sleep(500);
    }
  }
  return null;
}

/**  @param {Page} page */
async function manageCookie(page) {
  const selectors = [
    "::-p-xpath(//div[text()='Tout accepter']/ancestor::button)",
    "::-p-xpath(//div[text()='Accept all']/ancestor::button)",
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
    let scrollHeight = await safeEvaluate(page, () => document.body.scrollHeight);
    if (scrollHeight === null) break;
    const scrolled = await safeEvaluate(page, () => {
      window.scrollTo(0, document.body.scrollHeight);
      return true;
    });
    if (scrolled === null) break;
    await sleep(1200);
    const isHeighten = await safeEvaluate(
      page,
      (scrollHeight) => document.body.scrollHeight > scrollHeight,
      scrollHeight,
    );
    if (isHeighten === null) break;
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

  const moreBtnSelector =
    'a[aria-label="Autres résultats de recherche"],a[aria-label="More search results"]';
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

async function detectCaptcha(page) {
  const content = await page.content();
  return content.includes('À propos de cette page') || content.includes('About this page');
}

/**  @param {string} url */
async function get(url, maxPages) {
  const page = await connectBrowserPage();
  // first go to https://www.google.com/webhp?hl=en&gl=en and type kw
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  const scrapWait = process.env.SCRAP_WAIT ? parseInt(process.env.SCRAP_WAIT, 10) : 1000;
  // Spend the post-load wait moving the cursor like a human instead of sitting idle, so the
  // page accrues real mouse-dynamics signal (best-effort; never throws).
  await movePointerHuman(page);
  await sleep(scrapWait);
  const hasCaptcha = await detectCaptcha(page);
  let captchaSolved = false;
  if (hasCaptcha && (captchaToken || process.env.APP_ENV === 'test' || !isHeadless())) {
    console.log(' - try to solve captcha for ', url);
    try {
      await page.solveRecaptchas();
    } catch (error) {
      console.log(' - error solving captcha for ', url);
      console.log(error);
      return 'captcha';
    }
    await sleep(8000);
    captchaSolved = true;
  }
  if (await detectCaptcha(page)) {
    return 'captcha';
  }
  await manageCookie(page);
  await manageLoadMoreResultsViaInfiniteScroll(page, maxPages);
  await manageLoadMoreResultsViaBtn(page, maxPages);
  const content = await page.content();
  // Prepended marker lets PHP count captchas that were encountered AND solved (not just failures).
  return captchaSolved ? '<!--CAPTCHA_SOLVED-->\n' + content : content;
}

function isHeadless() {
  return !['false', '0'].includes(process.env.PUPPETEER_HEADLESS);
}
