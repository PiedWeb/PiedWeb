<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use PiedWeb\Curl\Response;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\Url;
use Symfony\Component\DomCrawler\Crawler;

final class GlobalTest extends TestCase
{
    /** Response[] */
    private array $response = [];

    public function getPage(string $url = 'https://piedweb.com'): ?string
    {
        if (isset($this->response[$url])) {
            return $this->response[$url];
        }

        $client = new ExtendedClient($url);
        $client
            ->setDefaultSpeedOptions()
            ->fakeBrowserHeader()
            ->setNoFollowRedirection()
            ->setMaximumResponseSize()
            ->setDownloadOnlyIf([Helper::class, 'checkStatusCode'])
            ->setMobileUserAgent();
        //  if ($this->proxy) { $client->setProxy($this->proxy); }
        $client->request();

        if ($client->getError() > 0) {
            return null;
        }

        return $this->response[$url] = $client->getResponse()->getBody();
    }

    public function testCanonical()
    {
        $url = new Url('https://piedweb.com');
        $canonical = new CanonicalExtractor($url, new Crawler($this->getPage()));
        $this->assertTrue($canonical->isCanonicalCorrect());
        $canonical = new CanonicalExtractor($url, new Crawler(str_replace('<link rel="canonical" href="https://piedweb.com/" />', '<link rel="canonical" href="/" />', $this->getPage('https://piedweb.com/'))));
        $this->assertFalse($canonical->isCanonicalCorrect());
        $this->assertTrue($canonical->isCanonicalPartiallyCorrect());

        $url = new Url('https://piedweb.com/seo');
        $canonical = new CanonicalExtractor($url, new Crawler(str_replace('<link rel="canonical" href="https://piedweb.com/seo" />', '<link rel="canonical" href="/seo" />', $this->getPage('https://piedweb.com/seo'))));
        $this->assertFalse($canonical->isCanonicalCorrect());
        $this->assertTrue($canonical->isCanonicalPartiallyCorrect());
    }
}
