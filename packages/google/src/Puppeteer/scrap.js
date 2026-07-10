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

puppeteer.use(RecaptchaPlugin(recaptchaOptions()));

/**
 * Pick the captcha solver from env: SOLVER ('2captcha' | 'capsolver' | 'none') + the matching token.
 * CapSolver rides the plugin's custom provider.fn (its createTask/getTaskResult API), so the plugin
 * still handles detection + token injection and no extra dependency is needed. SOLVER unset falls
 * back to whichever token exists (2captcha first) for backward compatibility.
 */
function recaptchaOptions() {
  const solver = process.env.SOLVER || '';
  const capsolverToken = process.env.PUPPETEER_CAPSOLVER_TOKEN;
  const twoCaptchaToken = process.env.PUPPETEER_2CAPTCHA_TOKEN;
  if (solver === 'none') return {};
  if ((solver === 'capsolver' || !twoCaptchaToken) && capsolverToken) {
    return { provider: { id: 'capsolver', token: capsolverToken, fn: capSolverGetSolutions }, visualFeedback: true };
  }
  if (twoCaptchaToken) {
    return { provider: { id: '2captcha', token: twoCaptchaToken }, visualFeedback: true };
  }
  return {};
}

/** Custom provider for puppeteer-extra-plugin-recaptcha: solves each captcha via the CapSolver API. */
async function capSolverGetSolutions(captchas = [], token = '') {
  const solutions = await Promise.all(captchas.map((c) => capSolverSolve(c, token)));
  return { solutions, error: solutions.find((s) => !!s.error) };
}

async function capSolverSolve(captcha, token) {
  const solution = { _vendor: captcha._vendor, provider: 'capsolver' };
  try {
    if (!captcha || !captcha.sitekey || !captcha.url || !captcha.id) throw new Error('missing captcha data');
    solution.id = captcha.id;
    const requestAt = new Date();
    const task = capSolverTask(captcha);
    const created = await capSolverApi('https://api.capsolver.com/createTask', { clientKey: token, task });
    if (created.errorId) throw new Error('createTask: ' + (created.errorDescription || created.errorCode));
    let result;
    for (let i = 0; i < 60; i++) {
      await sleep(2000);
      const r = await capSolverApi('https://api.capsolver.com/getTaskResult', { clientKey: token, taskId: created.taskId });
      if (r.errorId) throw new Error('getTaskResult: ' + (r.errorDescription || r.errorCode));
      if (r.status === 'ready') { result = r.solution || {}; break; }
    }
    const text = result && (result.gRecaptchaResponse || result.token);
    if (!text) throw new Error('no solution token');
    solution.providerCaptchaId = created.taskId;
    solution.text = text;
    solution.responseAt = new Date();
    solution.hasSolution = true;
    solution.duration = (solution.responseAt - requestAt) / 1000;
  } catch (error) {
    solution.error = 'capsolver error: ' + String((error && error.message) || error);
  }
  return solution;
}

/**
 * Build the CapSolver task for a detected captcha. On the commercial lane (PROXY_GATE + PROXY_USER
 * set) the reCAPTCHA is solved THROUGH the same sticky proxy IP Chrome egresses on, so the token is
 * minted from our egress IP and accepted by Google's IP-bound /sorry enterprise reCAPTCHA — a
 * ProxyLess token (solved from CapSolver's own IP) is always rejected. Direct/own-exits (no shared
 * gateway) and hCaptcha keep the ProxyLess task, i.e. the previous behaviour unchanged.
 */
function capSolverTask(captcha) {
  const proxy = captcha._vendor === 'hcaptcha' ? '' : capSolverProxy();
  if (proxy === '') {
    const type = captcha._vendor === 'hcaptcha' ? 'HCaptchaTaskProxyLess' : 'ReCaptchaV2TaskProxyLess';
    const task = { type, websiteURL: captcha.url, websiteKey: captcha.sitekey };
    if (captcha.isEnterprise) task.isEnterprise = true;
    return task;
  }
  const task = { type: 'ReCaptchaV2Task', websiteURL: captcha.url, websiteKey: captcha.sitekey, proxy };
  if (captcha.isEnterprise) task.isEnterprise = true;
  return task;
}

/**
 * CapSolver proxy string ("scheme:host:port:user:pass") so the reCAPTCHA is solved through the SAME
 * egress IP Chrome uses — the token then matches our IP and clears Google's IP-bound /sorry.
 *
 * Two sources, in order:
 *  - PROXY_SOLVER: a ready-made public proxy string (own-exit lane) — a bastion-hosted authenticated
 *    SOCKS5 that tunnels to the exit device, so CapSolver egresses on the SAME device IP Chrome does
 *    (Chrome reaches the device via a LOCAL socks the CapSolver servers can't, hence a separate public
 *    endpoint). Set by PuppeteerConnector only when configured; used verbatim.
 *  - else PROXY_GATE + PROXY_USER/PASS: the commercial gateway + sticky-session username Chrome shares.
 * Empty on direct/own-exits-without-a-solver-proxy and hCaptcha → ProxyLess task (previous behaviour).
 * Chrome's socks5h is mapped to socks5 (CapSolver has no remote-DNS scheme).
 */
function capSolverProxy() {
  const solver = process.env.PROXY_SOLVER || '';
  if (solver !== '') return solver;

  const gate = process.env.PROXY_GATE || '';
  const user = process.env.PROXY_USER || '';
  if (gate === '' || user === '') return '';
  const m = gate.match(/^(?:(https?|socks5h?):\/\/)?([^:/]+):(\d+)/i);
  if (m === null) return '';
  const scheme = (m[1] || 'http').toLowerCase().replace('socks5h', 'socks5');
  return [scheme, m[2], m[3], user, process.env.PROXY_PASS || ''].join(':');
}

async function capSolverApi(url, body) {
  const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  return res.json();
}

// Run the scrape only when invoked as the entry point; `require`-ing this module (e.g. from the
// fingerprint self-check to unit-test timezoneForUrl) must not launch a scrape.
if (require.main === module) {
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
}

module.exports = { timezoneForUrl };

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
  // The "unusual traffic" interstitial redirects to google.com/sorry/… in every UI language, so the
  // URL is a language-independent signal that catches blocks the localized body-text match misses
  // (e.g. a locale we don't string-match). The text check stays as a fallback for in-page captchas
  // served without the /sorry redirect.
  if (page.url().includes('/sorry/')) return true;
  const content = await page.content();
  return content.includes('À propos de cette page') || content.includes('About this page');
}

/**
 * Map the target Google host to the IANA timezone of its lane, so the emulated phone's clock matches
 * the locale it's browsing (an Android device on google.com with a Paris clock is a contradiction).
 * google.fr / .be / .ch → Europe/Paris; everything else (google.com EN lane) → America/New_York.
 * @param {string} u
 * @return {string|null}
 */
function timezoneForUrl(u) {
  try {
    const host = new URL(u).hostname;
    if (/\.(fr|be|ch|lu)$/i.test(host)) return 'Europe/Paris';
    return 'America/New_York';
  } catch (e) {
    return null;
  }
}

/**  @param {string} url */
async function get(url, maxPages) {
  // Seed the per-egress fingerprint (battery/connection) from the proxy gate so each IP gets a
  // stable-but-distinct value; direct lanes (no proxy) all share the box's single real device.
  const fpSeed = process.env.PROXY_GATE || 'direct';
  const page = await connectBrowserPage(true, {}, timezoneForUrl(url), fpSeed);
  // Credential-auth proxy (commercial residential): Chrome launched with the credential-free gate
  // can't authenticate the proxy, so answer the challenge here. Only set for a commercial route —
  // own-exit tunnels have no PROXY_USER, so this stays off and adds no request-interception overhead.
  if (process.env.PROXY_USER) {
    await page.authenticate({
      username: process.env.PROXY_USER,
      password: process.env.PROXY_PASS || '',
    });
  }
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
          // Result thumbnails are served extension-less (encrypted-tbn0.gstatic.com/images?q=tbn:…),
          // so they slip past the extension rules above; ~6KB each, ~7/SERP on image-rich results.
          // They never affect result parsing or pagination, and a data-saver client skips them too.
          '*encrypted-tbn*',
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
  // Whether a solver is configured at all — gates spending on solving vs. bailing out early.
  const captchaToken = process.env.PUPPETEER_CAPSOLVER_TOKEN || process.env.PUPPETEER_2CAPTCHA_TOKEN;
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
