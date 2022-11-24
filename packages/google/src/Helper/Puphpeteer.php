<?php

namespace PiedWeb\Google\Helper;

use Exception;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Puphpeteer\Resources\Browser;
use Nesk\Puphpeteer\Resources\Page;
use PiedWeb\Google\Logger;

class Puphpeteer
{
    /** @var Puppeteer[] */
    public static array $puppeteer = [];

    /** @var Browser[] */
    public static array $browser = [];

    /** @var Page[] */
    public static array $browserPage = [];

    public static string $pageContent = '';

    /**
     * @var string
     */
    final public const DEFAULT_LANGUAGE = 'fr-FR';

    public static string $currentKey = '';

    public static ?PuppeteerLogger $logger = null;

    public function getLogger(): PuppeteerLogger
    {
        return self::$logger ??= new PuppeteerLogger();
    }

    /**
     * Emulate a smartphone.
     *
     * @var array<string, string|array<string, int|bool>>
     */
    final public const EMULATE_OPTIONS_MOBILE = [
        'viewport' => [
            'width' => 412,
            'height' => 992,
            'deviceScaleFactor' => 3,
            'isMobile' => true,
            'hasTouch' => true,
            'isLandscape' => false,
        ],
        'userAgent' => 'Mozilla/5.0 (Linux; Android 10; SM-A305N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.210 Mobile Safari/537.36',
    ];

    /**
     * @var array<string, string|array<string, int|bool>>
     */
    final public const EMULATE_OPTIONS_DESKTOP = [
        'viewport' => [
            'width' => 1440,
            'height' => 900,
            'deviceScaleFactor' => 2,
            'isMobile' => false,
            'hasTouch' => false,
            'isLandscape' => true,
        ],
        'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.104 Safari/537.36',
    ];

    /**
     * @param array<string, mixed> $userOptions    array{ headless: bool, slowMo: int, read_timeout: int, idle_timeout: 9000 }
     * @param array<string, mixed> $emulateOptions array{ viewport: mixed, userAgent: string }
     *
     * @psalm-suppress UndefinedMagicMethod
     */
    public function instantiate(
        array $emulateOptions = [],
        string $language = '',
        array $userOptions = [
            'headless' => true,
            'slowMo' => 250,
            'read_timeout' => 9000,
            'idle_timeout' => 9000, ]
    ): self {
        $userOptions = [...$userOptions, ...['args' => ['--disable-web-security', '--lang='.('' !== $language ? $language : self::DEFAULT_LANGUAGE)]]];

        self::$currentKey = substr(md5(serialize($userOptions)), 0, 4);

        $userOptions['logger'] = self::$logger ??= new PuppeteerLogger();
        $userOptions['log_browser_console'] = true;
        $userOptions['log_node_console'] = true;

        if (isset(self::$puppeteer[self::$currentKey])) {
            $this->emulate([] !== $emulateOptions ? $emulateOptions : self::EMULATE_OPTIONS_MOBILE);

            return $this;
        }

        Logger::log('launching new Puppeteer instance `'.self::$currentKey.'`');
        self::$puppeteer[self::$currentKey] = new Puppeteer($userOptions);
        self::$browser[self::$currentKey] = self::$puppeteer[self::$currentKey]->launch([] !== $emulateOptions ? $emulateOptions : self::EMULATE_OPTIONS_MOBILE);
        self::$browserPage[self::$currentKey] = $this->getBrowserPage(true);
        self::$browserPage[self::$currentKey]->emulate([] !== $emulateOptions ? $emulateOptions : self::EMULATE_OPTIONS_MOBILE);

        return $this;
    }

    public function switchTo(string $key): self
    {
        self::$currentKey = $key;

        return $this;
    }

    /**
     * @noRector
     */
    public function getBrowserPage(bool $new = false): Page
    {
        if ('' === self::$currentKey || ! isset(self::$browser[self::$currentKey])) {
            $this->instantiate();
        }

        if ($new || ! isset(self::$browserPage[self::$currentKey])) {
            self::$browserPage[self::$currentKey] = (self::$browser[self::$currentKey] ?? throw new \LogicException())->newPage();
        }

        return self::$browserPage[self::$currentKey];
    }

    /**
     * @param array<string, mixed> $emulateOptions array{ viewport: mixed, userAgent: string }
     */
    public function emulate(array $emulateOptions): void
    {
        $this->getBrowserPage()->emulate($emulateOptions);
    }

    public function load(string $html, string $from = ''): string
    {
        if ('' !== $from) {
            $html = str_replace('<head>', '<head><base href="'.$from.'">', $html);
        }

        $this->getBrowserPage()->setContent($html);
        self::$pageContent = $this->getBrowserPage()->content();

        return self::$pageContent;
    }

    public function setCookie(string $name, string $value, string $domain): self
    {
        $cookie = \Safe\json_decode(\Safe\json_encode([
            ['name' => $name, 'value' => $value, 'domain' => $domain, 'expires' => time() + 3600 * 24 * 31 * 12 * 3],
        ]));
        $this->getBrowserPage()->setCookie($cookie[0]); // @phpstan-ignore-line

        return $this;
    }

    /**
     * @psalm-suppress UndefinedMagicMethod
     */
    public function get(string $url): string
    {
        $this->getBrowserPage()->goto($url, ['waitUntil' => 'domcontentloaded']);
        self::$pageContent = $this->getBrowserPage()->content();

        $this->manageMetaRefresh(pathinfo($url)['dirname']); // @phpstan-ignore-line

        self::$pageContent = $this->getBrowserPage()->content();

        return self::$pageContent;
    }

    /**
     * @psalm-suppress UndefinedMagicMethod
     */
    private function manageMetaRefresh(string $base = ''): void
    {
        if ($this->elementExists('[http-equiv=refresh]')) {
            // dd($this->getBrowserPage()->querySelectorEval('[http-equiv=refresh]', 'a => a.content'));
            $this->getBrowserPage()->waitForNavigation();
            Logger::log('follow meta refresh');
            $this->manageMetaRefresh($base);
        }
    }

    public function elementExists(string $selector): bool
    {
        return \count($this->getBrowserPage()->querySelectorAll($selector)) > 0;
    }

    public function close(): void
    {
        $bKey = self::$currentKey;
        if ('' === $bKey) {
            return;
        }

        Logger::log('close chrome `'.$bKey.'`');
        self::$browser[$bKey]->close();
        unset(self::$browser[$bKey]);
        unset(self::$browserPage[$bKey]);
        unset(self::$puppeteer[$bKey]);
        self::$currentKey = '';
    }

    public static function closeAll(): void
    {
        Logger::log('close All Chrome');
        foreach (self::$browser as $b) {
            try {
                $b->close();
            } catch (Exception) {
            }
        }

        self::$puppeteer = [];
        self::$browser = [];
        self::$browserPage = [];
    }

    public function __destruct()
    {
        // $this->closeAll();
    }
}
