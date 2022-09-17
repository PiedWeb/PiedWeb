<?php

namespace PiedWeb\Extractor;

use PiedWeb\Curl\ExtendedClient;
use Spatie\Robots\RobotsTxt;

final class RobotsTxtExtractor
{
    /**
     * @var array<string, RobotsTxt>
     */
    private static array $cache = [];

    public function get(Url $url): RobotsTxt
    {
        return self::$cache[$url->getOrigin()] ??= $this->directGet($url);
    }

    public function directGet(Url $url): RobotsTxt
    {
        $url = $url->getOrigin().'/robots.txt';

        $request = new ExtendedClient($url);
        $request
                ->setDefaultSpeedOptions()
                ->setDownloadOnly('0-500000')
                ->fakeBrowserHeader()
                ->setDesktopUserAgent();
        if (! $request->request()) {
            // todo log
            return new RobotsTxt('');
        }

        $response = $request->getResponse();

        if (false === stripos($response->getContentType(), 'text/plain')) {
            return new RobotsTxt('');
        }

        return new RobotsTxt($response->getBody());
    }
}
