/**
 * Lightweight behavioral signal for the stealth browser.
 *
 * Cloudflare Turnstile / DataDome score mouse dynamics: a human moves the cursor along
 * imperfect, eased, slightly-jittered arcs with variable latency, whereas a bot either
 * never moves the pointer (goto + sleep) or moves it in a perfect straight line. This
 * module adds that missing signal. Every function is best-effort and swallows its own
 * errors so it can never break a scrape.
 */

function rand(min, max) {
  return Math.random() * (max - min) + min;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// ease-in-out: speed ramps up then slows, like a real hand reaching a target
function easeInOut(t) {
  return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
}

/**
 * Move the cursor to a few random targets along eased, jittered paths.
 * @param {import('puppeteer').Page} page
 * @param {number} targets number of way-points to visit
 */
async function movePointerHuman(page, targets = 3) {
  try {
    const vp = page.viewport() ?? { width: 360, height: 640 };
    let x = rand(0, vp.width);
    let y = rand(0, vp.height);
    await page.mouse.move(x, y);

    for (let t = 0; t < targets; t++) {
      const tx = rand(0, vp.width);
      const ty = rand(0, vp.height);
      const steps = 15 + Math.floor(rand(0, 15));
      for (let i = 1; i <= steps; i++) {
        const e = easeInOut(i / steps);
        await page.mouse.move(x + (tx - x) * e + rand(-2, 2), y + (ty - y) * e + rand(-2, 2));
        await sleep(rand(6, 22));
      }
      x = tx;
      y = ty;
      await sleep(rand(40, 180)); // brief dwell at the target
    }
  } catch (e) {
    // best effort — never break the caller
  }
}

/**
 * A couple of human-paced wheel scrolls.
 * @param {import('puppeteer').Page} page
 * @param {number} bursts
 */
async function scrollHuman(page, bursts = 2) {
  try {
    for (let i = 0; i < bursts; i++) {
      await page.mouse.wheel({ deltaY: rand(120, 480) });
      await sleep(rand(180, 600));
    }
  } catch (e) {
    // best effort
  }
}

/**
 * Combined light behavioral pass: move + scroll + small dwell. Use on paths that otherwise
 * only goto+sleep (e.g. the Cloudflare-challenge fetch) to add interaction entropy.
 * @param {import('puppeteer').Page} page
 */
async function humanize(page) {
  await movePointerHuman(page);
  await scrollHuman(page);
  await sleep(rand(120, 400));
}

module.exports = { movePointerHuman, scrollHuman, humanize };
