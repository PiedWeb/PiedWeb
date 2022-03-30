<?php

namespace PiedWeb\Google\Helper;

use LogicException;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Puphpeteer\Resources\Browser;
use Nesk\Puphpeteer\Resources\Page;
use PiedWeb\Google\Logger;

class Puphpeteer
{
    public static ?Puppeteer $puppeteer;

    public static ?Browser $browser;

    public static ?Page $browserPage;

    public static string $pageContent = '';

    /**
     * @var string
     */
    public const DEFAULT_LANGUAGE = 'fr-FR';

    /**
     * Emulate a smartphone.
     *
     * @var array<string, string|array<string, int|bool>>
     */
    public const DEFAULT_EMULATE_OPTIONS = [
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
    public const EMULATE_OPTIONS_DESKTOP = [
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
     * @param array<string, mixed> $emulateOptions array{ viewport: mixed, userAgent: string }
     */
    public function instantiate(array $emulateOptions = [], string $language = ''): self
    {
        self::$puppeteer = new Puppeteer([
            'headless' => true,
            'slowMo' => 250,
            'read_timeout' => 9000,
            'idle_timeout' => 9000,
            'args' => ['--lang='.('' !== $language ? $language : self::DEFAULT_LANGUAGE)],
        ]);
        self::$browser = self::$puppeteer->launch();  // @phpstan-ignore-line
        self::$browserPage = self::$browser->newPage();
        $this->getBrowserPage()->emulate([] !== $emulateOptions ? $emulateOptions : self::DEFAULT_EMULATE_OPTIONS); // @phpstan-ignore-line

        return $this;
    }

    public function getBrowserPage(): Page
    {
        if (null === self::$browserPage) {
            throw new LogicException();
        }

        return self::$browserPage;
    }

    public function close(): void
    {
        if (null === self::$browser) {
            throw new LogicException();
        }

        Logger::log('close chrome');
        self::$browser->close();

        self::$puppeteer = null;
        self::$browser = null;
        self::$browserPage = null;
    }

    public function __destruct()
    {
        $this->close();
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

    public function setCookie(string $name, string $value, string $domain): void
    {
        $cookie = \Safe\json_decode(\Safe\json_encode([
            ['name' => $name, 'value' => $value, 'domain' => $domain, 'expires' => time() + 3600 * 24 * 31 * 12 * 3],
        ]));
        $this->getBrowserPage()->setCookie($cookie[0]); // @phpstan-ignore-line
    }

    public function get(string $url): string
    {
        $this->getBrowserPage()->goto($url, ['waitUntil' => 'domcontentloaded']); // @phpstan-ignore-line
        self::$pageContent = $this->getBrowserPage()->content();

        $this->manageMetaRefresh(pathinfo($url)['dirname']);

        self::$pageContent = $this->getBrowserPage()->content();

        return self::$pageContent;
    }

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
}
