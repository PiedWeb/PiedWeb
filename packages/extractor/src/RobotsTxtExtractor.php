<?php

namespace PiedWeb\Extractor;

use PiedWeb\Curl\ExtendedClient;
use Spatie\Robots\RobotsTxt;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

final class RobotsTxtExtractor
{
    /**
     * @var array<string, RobotsTxt>
     */
    private static array $cache = [];

    public function get(Url $url): RobotsTxt
    {
        return self::$cache[$url->getOrigin()] ??= new RobotsTxt($this->getBodyFromCache($url));
    }

    private function getBodyFromCache(Url $url): string
    {
        $cache = new FilesystemAdapter();

        /** @var string */
        $body = $cache->get('robotstxt_'.$url->getOrigin(), function (ItemInterface $item) use ($url): string {
            $item->expiresAfter(172800);

            return $this->getBody($url);
        });

        return $body;
    }

    private function getBody(Url $url): string
    {
        $url = $url->getOrigin().'/robots.txt';

        $request = new ExtendedClient($url);
        $request
                ->setDefaultSpeedOptions()
                ->setDownloadOnly('0-500000')
                ->fakeBrowserHeader()
                ->setDesktopUserAgent();
        if (! $request->request()) {
            return '';
        }

        $response = $request->getResponse();

        if (false === stripos($response->getContentType(), 'text/plain')) {
            return '';
        }

        return $response->getBody();
    }
}
