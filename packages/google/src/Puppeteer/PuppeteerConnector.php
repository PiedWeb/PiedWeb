<?php

namespace PiedWeb\Google\Puppeteer;

class PuppeteerConnector
{
    /**
     * @var array<string, string>
     */
    public static array $wsEndpointList = [];

    private static string $lastWsEndpointUsed = '';

    public function close(): void
    {
        $id = (string) \Safe\getmypid();
        foreach (static::$wsEndpointList as $key => $wsEndpoint) {
            if (str_starts_with($key, $id)) {
                exec('PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' node '.escapeshellarg(__DIR__.'/closeBrowser.js'));
                unset(static::$wsEndpointList[$key]);
            }
        }
    }

    /**
     * @param array<string|int> $args
     */
    public function execute(string $script, array $args = [], int $scrapWait = 1000): string
    {
        $wsEndpoint = $this->getWsEndpoint();

        $outputFileLog = sys_get_temp_dir().'/puppeteer-direct-'.\Safe\getmypid();

        $argsStr = '';
        foreach ($args as $arg) {
            if (! \is_string($arg)) { // so is int
                $argsStr .= ' '.$arg;

                continue;
            }

            $argsStr .= ' '.escapeshellarg($arg);
        }

        $cmd = 'SCRAP_WAIT='.$scrapWait.' PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' '
            .'node '.escapeshellarg($script).' '.$argsStr.' > '.escapeshellarg($outputFileLog);

        \Safe\exec($cmd);
        $rawOutput = \Safe\file_get_contents($outputFileLog); // going with file io to avoid truncated output

        return $rawOutput;
    }

    public function get(string $url, int $maxPages): string
    {
        $rawOutput = $this->execute(__DIR__.'/scrap.js', [$url, $maxPages]);

        if ('captcha' === trim($rawOutput)) {
            $this->close();
            $_SERVER['PUPPETEER_HEADLESS'] = false;
            $rawOutput = $this->execute(__DIR__.'/scrap.js', [$url, $maxPages], 30000);
            $this->close();
            $_SERVER['PUPPETEER_HEADLESS'] = true;
        }

        return $rawOutput;
    }

    public static function screenshot(string $path, string $wsEndpoint = ''): void
    {
        $wsEndpoint = $wsEndpoint ?: self::$lastWsEndpointUsed ?: throw new \Exception();
        $cmd = 'PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' '
           .'node '.escapeshellarg(__DIR__.'/screenshot.js').' '
           .escapeshellarg($path);

        \Safe\exec($cmd);
    }

    /**
     * @param string $language if language or proxy are changed, a new chrome will be launched
     */
    public function __construct(public string $language = 'fr', public string $proxy = '')
    {
    }

    /**
     *  @psalm-suppress NullableReturnStatement
     *  @psalm-suppress InvalidNullableReturnType
     *
     * @return string could be empty if create = false and no endpoint match
     */
    public function getWsEndpoint(bool $create = true): string
    {
        $id = \Safe\getmypid().'-'.$this->language.'-'.$this->proxy;

        if (isset(static::$wsEndpointList[$id])) {
            self::$lastWsEndpointUsed = static::$wsEndpointList[$id];

            return static::$wsEndpointList[$id];
        }

        if (! $create) {
            return '';
        }

        $cmd = '';

        if ('' !== $this->proxy) {
            $cmd .= 'PROXY_GATE='.escapeshellarg($this->proxy).' ';
        }

        if (isset($_SERVER['PUPPETEER_HEADLESS'])
            && \in_array($_SERVER['PUPPETEER_HEADLESS'], ['0', 'false', false], true)) {
            $cmd .= 'PUPPETEER_HEADLESS=0 ';
        }

        $outputFileLog = sys_get_temp_dir().'/puppeteer-direct-'.$id;
        $cmd .= 'node '.escapeshellarg(__DIR__.'/launchBrowser.js').' '.escapeshellarg($this->language)
                    .' > '.escapeshellarg($outputFileLog).' 2>&1 &';
        \Safe\exec($cmd);
        for ($i = 0; $i < 5; ++$i) {
            sleep(1);
            static::$wsEndpointList[$id] = trim((string) file_get_contents($outputFileLog));
            if ('' !== static::$wsEndpointList[$id]) {
                break;
            }
        }

        register_shutdown_function([$this, 'close']);

        self::$lastWsEndpointUsed = static::$wsEndpointList[$id];

        return static::$wsEndpointList[$id];
    }
}
