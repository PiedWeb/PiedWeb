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
  let retriesLeft = 3;
  // On a persistent detach (safeEvaluate returns null after its own retries), the main frame was
  // replaced by the continuous-scroll navigation. Wait for the new frame to attach and retry the
  // iteration rather than breaking with partial results; give up only once the budget is spent.
  const recover = async () => {
    if (retriesLeft-- <= 0) return false;
    await sleep(800);
    return true;
  };
  while (true) {
    let scrollHeight = await safeEvaluate(page, () => document.body.scrollHeight);
    if (scrollHeight === null) {
      if (await recover()) continue;
      break;
    }
    const scrolled = await safeEvaluate(page, () => {
      window.scrollTo(0, document.body.scrollHeight);
      return true;
    });
    if (scrolled === null) {
      if (await recover()) continue;
      break;
    }
    await sleep(1200);
    const isHeighten = await safeEvaluate(
      page,
      (scrollHeight) => document.body.scrollHeight > scrollHeight,
      scrollHeight,
    );
    if (isHeighten === null) {
      if (await recover()) continue;
      break;
    }
    retriesLeft = 3; // progress made → restore the full retry budget for the next iteration
    // limiter à maxPages pages
    if (!isHeighten || i >= maxPages) break;
    i++;
  }
}

/**
 * @param {Page} page
 * @param {int} maxPages
 */
async function manageLoadMoreResultsViaBtn(page, maxPages, retriesLeft = 3) {
  try {
    let navigationBlock = await page.$('h1 ::-p-text(Page Navigation)');
    if (navigationBlock === null) return;
    await navigationBlock.scrollIntoView();
    await sleep(250);

    // Google serves several pagination-control variants depending on the SERP/experiment:
    // the continuous-scroll "Autres résultats / More search results" anchor and the classic
    // "Page suivante / Next page" (a#pnnext). Match them all so we keep paginating across variants.
    const moreBtnSelector = [
      'a[aria-label="Autres résultats de recherche"]',
      'a[aria-label="More search results"]',
      'a[aria-label="Page suivante"]',
      'a[aria-label="Next page"]',
      'a#pnnext',
    ].join(',');
    let moreBtn = await page.$(moreBtnSelector);
    if (null === moreBtn) return console.error('Pas de boutons `Autres résultats`');

    await moreBtn.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await sleep(750);
    if (!(await moreBtn.isVisible())) return;

    // Tapping this button flips the SERP into continuous-results mode (URL gains #ip=1); Google then
    // appends the next result batches as you scroll. tap() is the honest touch event, but Google
    // overlays the anchor with a sibling div (e.g. .KxvlWc) that captures elementHandle.tap()'s
    // hit-test → it throws "not clickable" and a coordinate tap lands on the overlay without firing
    // the anchor's jsaction. Fall back to a DOM click, which dispatches on the anchor itself and
    // does engage #ip=1. tap() stays primary so SERPs where it works keep the genuine touch event.
    try {
      await moreBtn.tap();
    } catch (error) {
      await moreBtn.evaluate((el) => el.click());
    }
    await sleep(1000);

    // #ip=1 is active now → the remaining pages load on scroll, exactly like native continuous
    // scroll. Reuse that loader (it caps itself at maxPages) instead of re-clicking a button that
    // Google has already removed from the continuous-results DOM.
    return await manageLoadMoreResultsViaInfiniteScroll(page, maxPages);
  } catch (e) {
    // Google's continuous scroll can detach/replace the main frame mid-pagination (navigation
    // to #ip=1) — the raw page.$ / scrollIntoView / tap calls above then throw "detached Frame".
    // The detach is NOT terminal: a fresh page.$ targets the new main frame. So retry the step
    // (up to retriesLeft) on the now-attached frame; only accept partial results once the budget
    // is exhausted, instead of bubbling to get()'s catch and exit(1) on the whole scrape.
    const msg = String((e && e.message) || e);
    if (/detached frame|Execution context|context was destroyed|Target closed/i.test(msg)) {
      if (retriesLeft <= 0) return; // persistent detach → keep results already loaded
      await sleep(800); // let the new continuous-scroll frame attach & settle
      return await manageLoadMoreResultsViaBtn(page, maxPages, retriesLeft - 1);
    }
    throw e;
  }
}

async function detectCaptcha(page) {
  const content = await page.content();
  return content.includes('À propos de cette page') || content.includes('About this page');
}

/**  @param {string} url */
async function get(url, maxPages) {
  const page = await connectBrowserPage();
  // Sum real wire bytes (compressed body + headers) for every request this scrape makes across
  // all paginations — so PHP can account true SERP bandwidth, not just the final HTML size.
  // Best-effort: a CDP failure must never break the scrape.
  let netBytes = 0;
  try {
    const cdp = await page.createCDPSession();
    await cdp.send('Network.enable');
    cdp.on('Network.loadingFinished', (e) => {
      netBytes += e.encodedDataLength || 0;
    });
    // Bandwidth saver (opt-out via SCRAP_BLOCK_RESOURCES=false): drop the subresources a real
    // mobile Chrome in data-saver mode would also skip — images, media, fonts — plus Google's
    // telemetry beacons. We keep CSS (cookie/more-button visibility checks rely on it), the main
    // document and JS/XHR (results are JS-rendered). Paired with a Save-Data: on request header so
    // the pattern stays coherent with a genuine lite-mode client rather than reading as automation.
    // Done over the existing CDP session (no setRequestInterception — its added latency is a tell).
    if (!['false', '0'].includes(process.env.SCRAP_BLOCK_RESOURCES)) {
      await cdp.send('Network.setBlockedURLs', {
        urls: [
          '*.jpg',
          '*.jpeg',
          '*.png',
          '*.gif',
          '*.webp',
          '*.svg',
          '*.ico',
          '*.bmp',
          '*.mp4',
          '*.webm',
          '*.ogg',
          '*.woff',
          '*.woff2',
          '*.ttf',
          '*.otf',
          '*/gen_204*',
          '*/client_204*',
          '*play.google.com/log*',
          '*doubleclick.net*',
          '*googleadservices.com*',
          '*google-analytics.com*',
        ],
      });
      await cdp.send('Network.setExtraHTTPHeaders', { headers: { 'Save-Data': 'on' } });
    }
  } catch (e) {
    // ignore: bandwidth accounting / blocking is non-critical
  }
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
    // Diagnostics go to stderr only: stdout must stay clean so PuppeteerConnector finds the
    // NETBYTES/CAPTCHA_SOLVED markers (and the bare 'captcha' signal) at the very start.
    console.error(' - try to solve captcha for ', url);
    try {
      await page.solveRecaptchas();
    } catch (error) {
      console.error(' - error solving captcha for ', url);
      console.error(error);
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
  // Prepended markers (stripped by PuppeteerConnector, outermost first): NETBYTES carries the
  // real wire bytes for this scrape; CAPTCHA_SOLVED lets PHP count solved (not just failed) captchas.
  const withCaptcha = captchaSolved ? '<!--CAPTCHA_SOLVED-->\n' + content : content;
  return '<!--NETBYTES:' + Math.round(netBytes) + '-->\n' + withCaptcha;
}

function isHeadless() {
  return !['false', '0'].includes(process.env.PUPPETEER_HEADLESS);
}
