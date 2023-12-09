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

    public function get(Url|string $urlOrOrigin): RobotsTxt
    {
        $origin = \is_string($urlOrOrigin) ? $urlOrOrigin : $urlOrOrigin->getOrigin();

        return self::$cache[$origin] ??= new RobotsTxt($this->getBodyFromCache($origin));
    }

    private function getBodyFromCache(string $origin): string
    {
        $cache = new FilesystemAdapter();

        /** @var string */
        $body = $cache->get('robotstxt_'.$origin, function (ItemInterface $item) use ($origin): string {
            $item->expiresAfter(172800);

            return $this->getBody($origin);
        });

        return $body;
    }

    private function getBody(string $origin): string
    {
        $url = $origin.'/robots.txt';

        $request = new ExtendedClient($url);
        $request
                ->setDefaultSpeedOptions()
                ->setDownloadOnly('0-500000')
                ->fakeBrowserHeader()
                ->setDesktopUserAgent(); // TODO : Use CrawlConfig UA ?!
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
