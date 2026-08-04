/**
 * Pixel-position check for the SERP scraper.
 *
 *   node packages/google/tests/pixelpos-check.js
 *   composer test:pixelpos
 *
 * Regression cover for pixelPos coming back NEGATIVE, which aborted the whole `search:extract` run
 * on the consumer side (a SearchResult constructor threw, killing every keyword the run had left --
 * 1478 times between 2026-06-03 and 2026-08-03).
 *
 * boundingBox() is viewport-relative, so pixelPos.js used to scrollTo({ top: 0 }) and then read the
 * box without waiting for that reset to land. scrap.js's pagination loop leaves the SERP scrolled to
 * the bottom, so a read that won the race returned documentY minus a still-large scroll offset. One
 * node process is spawned per result, so the earliest results took the worst of it. The fix measures
 * rect.top + scrollY, which does not depend on where the page happens to be scrolled.
 *
 * Needs a browser (it drives the real pixelPos.js over CDP), no network. Exit 0 = pass, 1 = regressed.
 */

const path = require('path');
const { execFileSync } = require('child_process');

const puppeteer = require('puppeteer');

const SCRIPT = path.join(__dirname, '../src/Puppeteer/pixelPos.js');

// Fixture geometry: a 400px header, then five 185px rows each closed by a 1px border.
const ROWS = [
  { pos: 1, id: 'r1', documentY: 400 },
  { pos: 2, id: 'r2', documentY: 586 },
  { pos: 3, id: 'r3', documentY: 772 },
  { pos: 4, id: 'r4', documentY: 958 },
  { pos: 5, id: 'r5', documentY: 1144 },
];

const FIXTURE = `
  <style>
    /* A root scroller that animates: scrollTo() without an explicit behavior resolves to the
       computed scroll-behavior, so the reset lands asynchronously and the old code raced it. */
    html { scroll-behavior: smooth; }
    body { margin: 0 }
    #head { height: 400px }
    .r { height: 185px; border-bottom: 1px solid #ddd }
    #filler { height: 9000px }
    #hidden { display: none }
  </style>
  <div id="head">header</div>
  ${ROWS.map((r) => `<div class="r" id="${r.id}">result ${r.pos}</div>`).join('')}
  <div id="filler">more results…</div>
  <div id="hidden">not rendered</div>
`;

let failures = 0;

function check(name, condition, detail) {
  if (condition) {
    console.log('  ok   ' + name);

    return;
  }
  failures++;
  console.log('  FAIL ' + name + (detail ? ' — ' + detail : ''));
}

/** Run the real pixelPos.js exactly as SERPExtractor::getPixelPosFor() does. */
function measure(wsEndpoint, xpath) {
  const stdout = execFileSync('node', [SCRIPT, xpath], {
    env: { ...process.env, PUPPETEER_WS_ENDPOINT: wsEndpoint },
    encoding: 'utf8',
  });

  return parseInt(stdout.trim().split('\n').pop(), 10);
}

async function main() {
  const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });

  try {
    const wsEndpoint = browser.wsEndpoint();
    // connectBrowserPage() takes pages[0], so the fixture has to live there.
    const page = (await browser.pages())[0];
    await page.setViewport({ width: 412, height: 915 });
    await page.setContent(FIXTURE, { waitUntil: 'load' });

    console.log('pixelPos on a SERP left scrolled to the bottom');

    // Exactly what scrap.js's pagination loop leaves behind.
    await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' }));
    const scrolledTo = await page.evaluate(() => window.scrollY);
    check('the fixture is genuinely scrolled away from the top', scrolledTo > 2000, 'scrollY=' + scrolledTo);

    for (const row of ROWS) {
      const measured = measure(wsEndpoint, `//div[@id='${row.id}']`);

      // The crash: anything below zero reached a SMALLINT UNSIGNED column and threw.
      check('result ' + row.pos + ' is not negative', measured >= 0, 'got ' + measured);
      check(
        'result ' + row.pos + ' reports its document position',
        measured === row.documentY,
        'expected ' + row.documentY + ', got ' + measured,
      );
    }

    // Measuring must not disturb the page the scraper set up — the old reset scrolled it to the top.
    const scrollAfter = await page.evaluate(() => window.scrollY);
    check('measuring leaves the scroll position alone', scrollAfter === scrolledTo, 'scrollY=' + scrollAfter);

    console.log('\nno usable measurement answers 0');
    check('missing element', measure(wsEndpoint, "//div[@id='nope']") === 0);
    check('element with no box', measure(wsEndpoint, "//div[@id='hidden']") === 0);
  } finally {
    await browser.close();
  }
}

main()
  .then(() => {
    console.log(failures === 0 ? '\nPASS' : '\n' + failures + ' FAILED');
    process.exit(failures === 0 ? 0 : 1);
  })
  .catch((error) => {
    console.error('pixelpos-check crashed:', error);
    process.exit(1);
  });
