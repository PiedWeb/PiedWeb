/**
 * Captcha-detection resilience check for the SERP scraper.
 *
 *   node packages/google/tests/captcha-detect-check.js
 *   composer test:captcha-detect
 *
 * Regression cover for detectCaptcha aborting a whole scrape on a transient "detached Frame". Google
 * detaches/replaces the main frame mid-navigation (the /sorry→SERP redirect after a solve, or
 * continuous scroll to #ip=1); page.content() then threw straight up to get()'s catch → exit(1) → an
 * empty output PHP re-scraped from scratch (~119 aborted scrapes/day on one lane). detectCaptcha now
 * retries page.content() once on that transient error before giving up.
 *
 * Pure logic against fake pages: no browser, no network. Exit 0 = pass, 1 = a case regressed.
 */

const { detectCaptcha } = require('../src/Puppeteer/scrap');

let failures = 0;
function check(label, cond) {
  if (cond) {
    console.log('  ok   ' + label);
  } else {
    console.error('  FAIL ' + label);
    failures++;
  }
}

// Fake Page: url() is fixed; content() plays a scripted sequence of behaviours, one per call. A string
// entry is returned; an Error entry is thrown — so we can script "throw once, then succeed".
function fakePage(url, contentScript) {
  let i = 0;
  return {
    url: () => url,
    content: async () => {
      const step = contentScript[Math.min(i, contentScript.length - 1)];
      i++;
      if (step instanceof Error) throw step;
      return step;
    },
  };
}

const SERP = '<html><body>10 results</body></html>';
const detached = () => new Error("Attempted to use detached Frame 'ABC'.");

(async () => {
  // 1. /sorry in the URL short-circuits to true without ever touching content().
  check('sorry URL → captcha, no content() call', (await detectCaptcha(fakePage('https://www.google.com/sorry/index?q=x', [SERP]))) === true);

  // 2. The block-text fallback still fires when there is no /sorry redirect.
  check('French block text → captcha', (await detectCaptcha(fakePage('https://www.google.fr/search?q=x', ['<p>À propos de cette page</p>']))) === true);
  check('English block text → captcha', (await detectCaptcha(fakePage('https://www.google.com/search?q=x', ['<p>About this page</p>']))) === true);

  // 3. A clean SERP is not a captcha.
  check('clean SERP → no captcha', (await detectCaptcha(fakePage('https://www.google.fr/search?q=x', [SERP]))) === false);

  // 4. THE FIX: a transient detached-frame on the first content() call is retried, not fatal.
  check('detached once then SERP → recovers to no captcha', (await detectCaptcha(fakePage('https://www.google.fr/search?q=x', [detached(), SERP]))) === false);
  check('detached once then block text → recovers to captcha', (await detectCaptcha(fakePage('https://www.google.com/search?q=x', [detached(), '<p>About this page</p>']))) === true);

  // 5. A persistent detach (still thrown after the one retry) is re-thrown, preserving the last-resort
  //    exit(1)+re-scrape rather than silently banking a page we could not read.
  let threw = false;
  try {
    await detectCaptcha(fakePage('https://www.google.fr/search?q=x', [detached(), detached()]));
  } catch (e) {
    threw = /detached frame/i.test(String(e.message));
  }
  check('persistent detach → re-throws after the single retry', threw);

  // 6. A NON-transient content() error is not swallowed by the retry path either.
  let threwOther = false;
  try {
    await detectCaptcha(fakePage('https://www.google.fr/search?q=x', [new Error('boom')]));
  } catch (e) {
    threwOther = /boom/.test(String(e.message));
  }
  check('unrelated error → re-throws immediately', threwOther);

  if (failures > 0) {
    console.error('\ncaptcha-detect-check: ' + failures + ' failure(s)');
    process.exit(1);
  }
  console.log('\ncaptcha-detect-check: all cases passed');
})();
