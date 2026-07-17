const { Browser } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const { exec, execFile, spawn } = require('child_process');
const { promisify } = require('util');
const fs = require('fs');
const path = require('path');
const execAsync = promisify(exec);
const execFileAsync = promisify(execFile);

puppeteer.use(StealthPlugin());

/**
 * Path to the Chrome-scoped Android font profile (FONTCONFIG_FILE), or null if it should not apply.
 * Skips when the operator already set FONTCONFIG_FILE, when opted out (SCRAP_FONT_PROFILE=0/false), or
 * when the Android font dirs the profile references are absent (so a bare install keeps system fonts
 * instead of ending up with no fonts at all).
 * @return {string|null}
 */
function getFontConfigFile() {
  if (process.env.FONTCONFIG_FILE) {
    return process.env.FONTCONFIG_FILE;
  }
  if (['0', 'false'].includes(process.env.SCRAP_FONT_PROFILE || '')) {
    return null;
  }
  const hasAndroidFonts =
    fs.existsSync('/usr/share/fonts/truetype/roboto') && fs.existsSync('/usr/share/fonts/truetype/noto');
  if (!hasAndroidFonts) {
    return null;
  }

  return path.join(__dirname, 'android-fonts.conf');
}

/**
 * Chrome's headless GPU process now initialises WebGL through ANGLE's Vulkan-backed SwiftShader,
 * which enumerates WSI surface extensions via XCB even for off-screen rendering — with no X server
 * at all that init fails (ui/gl/angle_platform_impl.cc DisplayVkXcb "xcb_connect() failed") and every
 * WebGL context comes back null, silently defeating the getParameter/getExtension overrides in
 * emulate() (they only patch an existing context's prototype). Xvfb supplies that X server headlessly.
 * Skips when opted out (SCRAP_XVFB=0/false), when the operator's existing DISPLAY already answers
 * (verified via xdpyinfo, not just presence — a stale/leftover DISPLAY pointing at a dead X server is
 * indistinguishable from a working one by env var alone), or when Xvfb isn't installed (bare install
 * keeps the prior no-WebGL behavior instead of failing hard).
 * @return {Promise<{display: string, proc: import('child_process').ChildProcess}|null>}
 */
async function launchVirtualDisplay() {
  if (['0', 'false'].includes(process.env.SCRAP_XVFB || '')) return null;
  if (process.env.DISPLAY) {
    try {
      await execFileAsync('xdpyinfo', ['-display', process.env.DISPLAY]);
      return null; // existing DISPLAY answers — nothing to do
    } catch (e) {
      /* DISPLAY set but unreachable/stale, or xdpyinfo missing — fall through and provide our own */
    }
  }
  try {
    await execFileAsync('which', ['Xvfb']);
  } catch (e) {
    return null;
  }
  for (let attempt = 0; attempt < 5; attempt++) {
    const n = 90 + Math.floor(Math.random() * 900);
    if (fs.existsSync(`/tmp/.X11-unix/X${n}`)) continue;
    const display = ':' + n;
    const proc = spawn('Xvfb', [display, '-screen', '0', '1x1x24', '-nolisten', 'tcp'], {
      stdio: 'ignore',
    });
    const ready = await new Promise((resolve) => {
      proc.once('error', () => resolve(false));
      proc.once('exit', () => resolve(false));
      const check = setInterval(() => {
        if (fs.existsSync(`/tmp/.X11-unix/X${n}`)) {
          clearInterval(check);
          resolve(true);
        }
      }, 50);
      setTimeout(() => {
        clearInterval(check);
        resolve(fs.existsSync(`/tmp/.X11-unix/X${n}`));
      }, 2000);
    });
    if (ready) return { display, proc };
    proc.kill();
  }
  return null;
}

/**
 * Tue uniquement les processus Chrome utilisant le même userDataDir
 * @param {string} userDataDir - Le répertoire de données utilisateur
 */
async function killExistingBrowserProcesses(userDataDir) {
  const escapedUserDataDir = userDataDir.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  try {
    const killCommand = `pkill -f -- "--user-data-dir.*${escapedUserDataDir}"`;
    await execAsync(killCommand);
    await new Promise((resolve) => setTimeout(resolve, 500));
  } catch (error) {
    // Aucun processus trouvé, c'est normal
  }
}

function getAcceptLanguage(language) {
  if (language === 'en' || language === 'en-US') {
    return 'en-US,en;q=0.9';
  }

  if (language === 'fr-CA') {
    return 'fr-CA,en;q=0.9';
  }

  return 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7';
}

/**
 * ICU/Intl default locale. Chrome inherits it from the process locale, NOT from --lang, so on a
 * French box the EN lane formats dates/numbers/currency in French — an en-US phone that prints
 * "1,00 $US" is a locale↔language contradiction (CreepJS flags it as a lie). Pin the launch env to
 * the lane so Intl matches navigator.language.
 * @param {string} language
 */
function getLocaleEnv(language) {
  if (language === 'en' || language === 'en-US') {
    return 'en_US.UTF-8';
  }

  if (language === 'fr-CA') {
    return 'fr_CA.UTF-8';
  }

  return 'fr_FR.UTF-8';
}

/**
 * Lance un navigateur Puppeteer avec les options spécifiées
 * @param {boolean|null} headless - Mode sans interface graphique (null = auto-détection)
 * @param {string|null} windowSize - Taille de la fenêtre au format "largeur,hauteur"
 * @param {string|null} proxy - Serveur proxy à utiliser
 * @param {string|null} userDataDir - Répertoire de données utilisateur
 * @param {string|null} profile - Profil Chrome à utiliser
 * @param {string|null} lang - Code de langue (ex: 'en', 'fr', 'fr-CA')
 * @param {string|null} chromeBin - Chemin vers l'exécutable Chrome
 * @returns {Promise<Browser>} Une promesse qui résout vers l'instance du navigateur
 */
async function launchBrowser(
  headless = null,
  windowSize = null,
  proxy = null,
  userDataDir = null,
  profile = null,
  lang = null,
  chromeBin = null,
) {
  headless =
    headless === false
      ? false
      : ['false', '0'].includes(process.env.PUPPETEER_HEADLESS)
        ? false
        : true;

  windowSize = windowSize ?? process.env.PUPPETEER_WINDOW_SIZE ?? process.argv[3] ?? '360,840';
  proxy = proxy ?? process.env.PROXY_GATE ?? '';
  userDataDir =
    userDataDir ??
    process.env.PUPPETEER_USER_DATA_DIR ??
    (profile === null ? '/tmp/default_pp_profile' : null); // it permits to avoid to solve captcha manually multiple times during tests and first usage
  lang = lang ?? process.env.PUPPETEER_LANG ?? process.argv[2] ?? 'en';
  chromeBin = chromeBin ?? process.env.CHROME_BIN ?? '/usr/bin/google-chrome';
  // default_pp_profile permit to avoid to solve captcha manually during tests and first usage, else defining profile is strongly recommended
  profile = profile ?? process.env.PUPPETEER_PROFILE ?? null;

  // Nettoyer les processus existants utilisant le même userDataDir
  if (userDataDir) {
    await killExistingBrowserProcesses(userDataDir + (profile ? '/' + profile : ''));
  }

  const localeEnv = getLocaleEnv(lang);
  const fontConfigFile = getFontConfigFile();
  // Only the headless path needs a stand-in X server; a headed run (PUPPETEER_HEADLESS=0) is already
  // driven from a real display.
  const virtualDisplay = headless ? await launchVirtualDisplay() : null;
  const options = {
    // pipe: true, // disable endpoint
    // Pin the process locale to the lane so ICU/Intl formats in the target language, not the box's,
    // and (when the Android fonts are present) scope Chrome's fonts to the Roboto/Noto profile so its
    // font-enumeration fingerprint matches a real phone instead of the Linux host.
    env: {
      ...process.env,
      LANG: localeEnv,
      LC_ALL: localeEnv,
      LANGUAGE: localeEnv.split('.')[0],
      ...(fontConfigFile ? { FONTCONFIG_FILE: fontConfigFile } : {}),
      ...(virtualDisplay ? { DISPLAY: virtualDisplay.display } : {}),
    },
    defaultViewport: null,
    // Startup timeout for the WS endpoint. Default 30s (unchanged); opt-in to a
    // longer value via PUPPETEER_LAUNCH_TIMEOUT to absorb slow snap cold starts.
    timeout: Number(process.env.PUPPETEER_LAUNCH_TIMEOUT) || 30000,
    headless: headless,
    ...(chromeBin && { executablePath: chromeBin }),
    ...(userDataDir && { userDataDir: userDataDir }),
    args: [
      ...[
        // SERP scraper needs cross-origin relaxation; the flag is a bot tell, so let the
        // Cloudflare-fetch path (or any caller) drop it via PUPPETEER_DISABLE_WEB_SECURITY=0.
        ...(['false', '0'].includes(process.env.PUPPETEER_DISABLE_WEB_SECURITY)
          ? []
          : ['--disable-web-security']),
        '--lang=' + lang,
        '--accept-lang=' + getAcceptLanguage(lang),
        '--no-sandbox',
        '--disable-setuid-sandbox',

        // '--single-process',
        // '--no-zygote',
      ],
      ...(proxy ? ['--proxy-server=' + proxy] : []),
      ...['--window-size=' + windowSize],
      // the first arg permits to disable puppeteer default userDataDir Args
      ...(profile
        ? [
            //'--user-data-dir',
            '--profile-directory=' + profile,
          ]
        : []),
    ],
    ignoreDefaultArgs: ['--password-store=basic', '--use-mock-keychain', '--disable-extensions'],
  };

  /** @type {Browser} */
  const browser = await puppeteer.launch(options);

  if (virtualDisplay) {
    const stopVirtualDisplay = () => {
      try {
        virtualDisplay.proc.kill();
      } catch (e) {
        /* already gone */
      }
    };
    browser.once('disconnected', stopVirtualDisplay);
    process.once('exit', stopVirtualDisplay);
  }

  console.log(await browser.wsEndpoint());

  return browser;
}

module.exports = { launchBrowser, getFontConfigFile };
