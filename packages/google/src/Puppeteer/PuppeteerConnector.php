<?php

namespace PiedWeb\Google\Puppeteer;

class PuppeteerConnector
{
    /**
     * @var array<string, string>
     */
    public static array $wsEndpointList = [];

    private static string $lastWsEndpointUsed = '';

    public static function close(): void
    {
        $id = (string) \Safe\getmypid();
        foreach (static::$wsEndpointList as $key => $wsEndpoint) {
            if (str_starts_with($key, $id)) {
                exec('PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' node '.escapeshellarg(__DIR__.'/closeBrowser.js'));
            }
        }
    }

    public static function get(string $url, string $language = 'fr', string $proxy = ''): string
    {
        $wsEndpoint = self::getWsEndpoint($language, $proxy);

        $outputFileLog = sys_get_temp_dir().'/puppeteer-direct-'.\Safe\getmypid();
        $cmd = 'PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' '
            .'node '.escapeshellarg(__DIR__.'/scrap.js').' '
            .escapeshellarg($url)
            .' > '.escapeshellarg($outputFileLog);

        \Safe\exec($cmd);
        $rawOutput = \Safe\file_get_contents($outputFileLog); // going with file io to avoid truncated output

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

    public static function getWsEndpoint(string $language = 'fr', string $proxy = ''): string
    {
        $id = \Safe\getmypid().'-'.$language.'-'.$proxy;

        if (isset(static::$wsEndpointList[$id])) {
            self::$lastWsEndpointUsed = static::$wsEndpointList[$id];

            return static::$wsEndpointList[$id];
        }

        $cmd = '';

        if ('' !== $proxy) {
            $cmd .= 'PROXY_GATE='.escapeshellarg($proxy).' ';
        }

        $outputFileLog = sys_get_temp_dir().'/puppeteer-direct-'.$id;
        $cmd .= 'node '.escapeshellarg(__DIR__.'/launchBrowser.js').' '.escapeshellarg($language)
                    .' > '.escapeshellarg($outputFileLog).' 2>&1 &';
        \Safe\exec($cmd);
        for ($i = 0; $i < 5; ++$i) {
            sleep(1);
            static::$wsEndpointList[$id] = trim((string) file_get_contents($outputFileLog));
            if ('' !== static::$wsEndpointList[$id]) {
                break;
            }
        }

        register_shutdown_function([__CLASS__, 'close']);

        self::$lastWsEndpointUsed = static::$wsEndpointList[$id];

        return static::$wsEndpointList[$id];
    }
}
