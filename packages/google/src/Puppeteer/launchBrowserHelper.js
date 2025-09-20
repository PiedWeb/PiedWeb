const { Browser } = require('puppeteer');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

puppeteer.use(StealthPlugin());

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
  if (language === 'en') {
    return 'en-US,en;q=0.9';
  }

  if (language === 'fr-CA') {
    return 'fr-CA,en;q=0.9';
  }

  return 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7';
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
  chromeBin = null
) {
  headless =
    headless === false
      ? false
      : ['false', '0'].includes(process.env.PUPPETEER_HEADLESS)
      ? false
      : true;
  windowSize = windowSize ?? process.env.PUPPETEER_WINDOW_SIZE ?? process.argv[3] ?? '360,840';
  proxy = proxy ?? process.env.PROXY_GATE ?? '';
  userDataDir = userDataDir ?? process.env.PUPPETEER_USER_DATA_DIR ?? null;
  lang = lang ?? process.env.PUPPETEER_LANG ?? process.argv[2] ?? 'en';
  chromeBin = chromeBin ?? process.env.CHROME_BIN ?? '/usr/bin/google-chrome';
  profile = profile ?? process.env.PUPPETEER_PROFILE ?? null;

  // Nettoyer les processus existants utilisant le même userDataDir
  if (userDataDir) {
    await killExistingBrowserProcesses(userDataDir + (profile ? '/' + profile : ''));
  }

  const options = {
    // pipe: true, // disable endpoint
    defaultViewport: null,
    headless: headless,
    ...(chromeBin && { executablePath: chromeBin }),
    ...(userDataDir && { userDataDir: userDataDir }),
    args: [
      ...[
        '--disable-web-security',
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
    ignoreDefaultArgs: ['--password-store=basic', '--use-mock-keychain'],
  };

  /** @type {Browser} */
  const browser = await puppeteer.launch(options);

  console.log(await browser.wsEndpoint());

  return browser;
}

module.exports = { launchBrowser };
