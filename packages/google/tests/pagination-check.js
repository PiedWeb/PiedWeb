/**
 * Pagination-resilience check for the SERP scraper.
 *
 *   node packages/google/tests/pagination-check.js
 *   composer test:pagination
 *
 * Regression cover for a truncated capture being banked as a complete SERP. Google renders the
 * pagination block lazily, so a single `page.$` lookup could miss it on a page that had not settled
 * yet; manageLoadMoreResultsViaBtn then returned on the spot and the SERP was stored with only the
 * first batch of results — 7 organic where the same keyword had 15-16 hours earlier, with nothing
 * logged anywhere. findNavigationBlock now polls for a bounded window instead.
 *
 * Pure logic against fake pages: no browser, no network. Exit 0 = pass, 1 = a case regressed.
 */

const {
  findNavigationBlock,
  manageLoadMoreResultsViaInfiniteScroll,
} = require('../src/Puppeteer/scrap');

let failures = 0;

function check(name, condition, detail) {
  if (condition) {
    console.log('  ok   ' + name);

    return;
  }
  failures++;
  console.log('  FAIL ' + name + (detail ? ' — ' + detail : ''));
}

/** A page whose pagination block shows up only from the nth lookup onward (Infinity = never). */
function pageWithBlockFromLookup(n) {
  let lookups = 0;

  return {
    get lookups() {
      return lookups;
    },
    $: async () => {
      lookups++;

      return lookups >= n ? { it: 'is the navigation block' } : null;
    },
  };
}

/** A page driving safeEvaluate through a scripted sequence of return values. */
function pageReturning(values) {
  let i = 0;

  return { evaluate: async () => values[i++] };
}

async function main() {
  console.log('findNavigationBlock');

  // Present on the first look: must return at once and not spend any of the wait budget.
  let page = pageWithBlockFromLookup(1);
  let started = Date.now();
  let block = await findNavigationBlock(page, 2000);
  let elapsed = Date.now() - started;
  check('returns the block immediately when it is already there', block !== null);
  check('spends no wait budget when the block is there', elapsed < 150, elapsed + 'ms');

  // The regression itself: the block appears late. The old single-lookup code returned null here
  // and silently banked a partial page.
  page = pageWithBlockFromLookup(3);
  started = Date.now();
  block = await findNavigationBlock(page, 2000);
  elapsed = Date.now() - started;
  check('finds a block that only appears on a later poll', block !== null);
  check('polled more than once to get it', page.lookups >= 3, page.lookups + ' lookups');
  check('waited before finding it', elapsed >= 300, elapsed + 'ms');

  // Genuinely absent (a real short SERP): give up, but only after the bounded window.
  page = pageWithBlockFromLookup(Infinity);
  started = Date.now();
  block = await findNavigationBlock(page, 900);
  elapsed = Date.now() - started;
  check('gives up when the block is genuinely absent', block === null);
  check('honours the wait budget before giving up', elapsed >= 900, elapsed + 'ms');
  check('does not overrun the budget', elapsed < 2000, elapsed + 'ms');

  // A zero budget must not regress into an unbounded loop: exactly one lookup, no sleeping.
  page = pageWithBlockFromLookup(Infinity);
  started = Date.now();
  block = await findNavigationBlock(page, 0);
  elapsed = Date.now() - started;
  check('with a zero budget, looks exactly once', page.lookups === 1, page.lookups + ' lookups');
  check('with a zero budget, returns at once', block === null && elapsed < 150, elapsed + 'ms');

  console.log('manageLoadMoreResultsViaInfiniteScroll');

  // scrollHeight, scrolled, isHeighten -> page never grew.
  const flat = await manageLoadMoreResultsViaInfiniteScroll(pageReturning([1000, true, false]), 3);
  check('reports false when the page never extended', flat === false, 'got ' + flat);

  // Same sequence but the page did grow: the caller must see that scrolling did the job.
  const grew = await manageLoadMoreResultsViaInfiniteScroll(pageReturning([1000, true, true]), 1);
  check('reports true when the page extended', grew === true, 'got ' + grew);
}

main()
  .then(() => {
    console.log(failures === 0 ? '\nPASS' : '\n' + failures + ' FAILED');
    process.exit(failures === 0 ? 0 : 1);
  })
  .catch((error) => {
    console.error('pagination-check crashed:', error);
    process.exit(1);
  });
