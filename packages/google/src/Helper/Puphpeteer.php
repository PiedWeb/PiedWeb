<?php

namespace PiedWeb\Google\Helper;

use Nesk\Puphpeteer\Puppeteer;
use Nesk\Puphpeteer\Resources\Browser;
use Nesk\Puphpeteer\Resources\Page;
use Nesk\Rialto\Data\BasicResource;
use Nesk\Rialto\Exceptions\Node\FatalException;

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
    final public const string DEFAULT_LANGUAGE = 'fr-FR';

    public static string $currentKey = '';

    public static ?PuppeteerLogger $logger = null;

    public function getLogger(): PuppeteerLogger
    {
        return self::$logger ??= new PuppeteerLogger();
    }

    /**
     * Emulate a smartphone.
     *
     * @var array<string, string|bool|array<string, int|bool>>
     */
    final public const array EMULATE_OPTIONS_MOBILE = [
        'viewport' => [
            'width' => 412,
            'height' => 992,
            'deviceScaleFactor' => 3,
            'isMobile' => true,
            'hasTouch' => true,
            'isLandscape' => false,
        ],
        'userAgent' => 'Mozilla/5.0 (Linux; Android 10; SM-A305N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.210 Mobile Safari/537.36',
        'headless' => true, // false, //'new',
    ];

    /**
     * @var array<string, string|array<string, int|bool>>
     */
    final public const array EMULATE_OPTIONS_DESKTOP = [
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
            'headless' => 'new',
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

        $userOptions['js_extra'] = "
            const puppeteer = require('puppeteer-extra');
            const StealthPlugin = require('puppeteer-extra-plugin-stealth');
            puppeteer.use(StealthPlugin());
            instruction.setDefaultResource(puppeteer);
        ";

        $this->getLogger()->info('launching new Puppeteer instance `'.self::$currentKey.'`');

        self::$puppeteer[self::$currentKey] = new Puppeteer($userOptions);
        self::$browser[self::$currentKey] = self::$puppeteer[self::$currentKey]->launch(
            array_merge(
                [] !== $emulateOptions ? $emulateOptions : self::EMULATE_OPTIONS_MOBILE,
                // ['executablePath' => '/snap/bin/chromium',]
                // ['headless' => false]
            )
        );

        self::$browserPage[self::$currentKey] = $this->getBrowserPage();
        self::$browserPage[self::$currentKey]->emulate([] !== $emulateOptions ? $emulateOptions : self::EMULATE_OPTIONS_MOBILE);

        return $this;
    }

    public function switchTo(string $key): self
    {
        self::$currentKey = $key;

        return $this;
    }

    public function getBrowserPage(bool $new = false): Page
    {
        if ('' === self::$currentKey || ! isset(self::$browser[self::$currentKey])) {
            $this->instantiate();
        }

        if (! isset(self::$browser[self::$currentKey])) {
            throw new \LogicException();
        }

        [$currentPage] = self::$browser[self::$currentKey]->pages();
        self::$browserPage[self::$currentKey] = $currentPage;

        if ($new || ! isset(self::$browserPage[self::$currentKey])) {
            self::$browserPage[self::$currentKey] = self::$browser[self::$currentKey]->newPage();
        }

        if (self::$browserPage[self::$currentKey]::class === BasicResource::class) {
            dump($new);
            dump(self::$browser[self::$currentKey]::class);
            // self::$browserPage[self::$currentKey] = self::$browser[self::$currentKey]->newPage();
            dump(self::$browserPage[self::$currentKey]::class);
            self::$browserPage[self::$currentKey] = self::$browser[self::$currentKey]->newPage();
            dd(self::$browser[self::$currentKey]::class);
            // dd(self::$browserPage[self::$currentKey]);
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
    private function managePosition(): void
    {
        $btn = $this->getBrowserPage()->querySelector("::-p-xpath(//*[contains(text(), 'Pas maintenant')])");
        if (null === $btn) {
            return;
        }

        $this->getLogger()->info('Accept Cookie');
        if (! $btn->isVisible()) {
            return;
        }

        $btn->tap($btn);
        usleep(500_000);
    }

    /**
     * @psalm-suppress UndefinedMagicMethod
     */
    private function manageCookie(): void
    {
        $cookieAcceptBtn = $this->getBrowserPage()->querySelector('::-p-xpath('."//div[text()='Tout accepter']/ancestor::button".')');
        if (null === $cookieAcceptBtn) {
            return;
        }

        $this->getLogger()->info('Accept Cookie');
        $cookieAcceptBtn->scrollIntoView($cookieAcceptBtn);
        if (! $cookieAcceptBtn->isVisible()) {
            return;
        }

        $cookieAcceptBtn->tap($cookieAcceptBtn);
        usleep(1_000_000);
    }

    private int $clickForMoreResults = 0;

    /** @psalm-suppress UndefinedMagicMethod */
    private function clickMoreResults(): void
    {
        $blockContainingMoreResultsBtn = $this->getBrowserPage()->querySelector('h1 ::-p-text(Page Navigation)');

        if (null !== $blockContainingMoreResultsBtn) {
            $blockContainingMoreResultsBtn->scrollIntoView($blockContainingMoreResultsBtn);
        }
        usleep(350000);

        // dump(null !== $blockContainingMoreResultsBtn ? 'moreResults not exists' : 'moreResults exists');
        // dump(null !== $btn ? 'moreResults A not exists' : 'moreResults A exists');
        // $this->getBrowserPage()->screenshot(['path' => './debug/dump.png',  ]);

        $btn = $this->getBrowserPage()->querySelector('a[aria-label="Autres résultats de recherche"]');
        if (null === $btn) {
            $this->getLogger()->info('Pas de boutons `Autres résultats`');

            return;
        }

        $this->getLogger()->info('Click `Autres résultats de recherche`');

        $btn->scrollIntoView($btn);
        usleep(350000);
        if (! $btn->isVisible()) {
            return;
        }

        $btn->tap($btn);

        ++$this->clickForMoreResults;

        if ($this->clickForMoreResults < 3) {
            $this->clickMoreResults();
        }
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function getInfiniteScrolled(string $url, int $maxScroll = 10): string
    {
        $this->get($url);
        // file_put_contents('debug.html', $this->getBrowserPage()->content());
        $this->manageCookie();
        $this->managePosition();

        for ($i = 1; true; ++$i) {
            $scrollHeight = $this->getBrowserPage()->evaluate('document.body.scrollHeight'); // @phpstan-ignore-line
            $this->getBrowserPage()->evaluate('window.scrollTo(0, document.body.scrollHeight)'); // @phpstan-ignore-line
            usleep(350000);
            $isHeighten = $this->getBrowserPage()->evaluate('document.body.scrollHeight > '.$scrollHeight.''); // @phpstan-ignore-line
            // dump($scrollHeight, $isHeighten);
            // $this->getBrowserPage()->screenshot(['path' => './debug/dump.png',  ]);
            if (! $isHeighten || $i > $maxScroll) {
                break;
            }
        }

        $this->clickForMoreResults = 0;

        try {
            $this->clickMoreResults();
            // utiliser la position exacte
        } catch (FatalException $e) {
            $this->getLogger()->info('btn found but not clickable');
        }

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
            $this->getLogger()->info('follow meta refresh');
            $this->manageMetaRefresh($base);
        }
    }

    public function elementExists(string $selector): bool
    {
        return [] !== $this->getBrowserPage()->querySelectorAll($selector);
    }

    public function close(): void
    {
        $bKey = self::$currentKey;
        if ('' === $bKey) {
            return;
        }

        $this->getLogger()->info('close chrome `'.$bKey.'`');
        self::$browser[$bKey]->close();
        unset(self::$browser[$bKey]);
        unset(self::$browserPage[$bKey]);
        unset(self::$puppeteer[$bKey]);
        self::$currentKey = '';
    }

    public static function closeAll(): void
    {
        // $this->getLogger()->info('close All Chrome');
        foreach (self::$browser as $b) {
            try {
                $b->close();
            } catch (\Exception) {
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
