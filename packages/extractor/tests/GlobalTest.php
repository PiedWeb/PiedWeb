<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use PiedWeb\Curl\Response;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\HrefLangExtractor;
use PiedWeb\Extractor\TextData;
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
            ->setDownloadOnlyIf(Helper::checkStatusCode(...))
            ->setMobileUserAgent();
        //  if ($this->proxy) { $client->setProxy($this->proxy); }
        $client->request();

        if ($client->getError() > 0) {
            return null;
        }

        return $this->response[$url] = $client->getResponse()->getBody();
    }

    public function testCanonical(): void
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

    public function testTextDataExtractor(): void
    {
        $rawHtml = $this->getPage('https://piedweb.com/');
        $crawler = new Crawler($rawHtml);

        $textData = new TextData($rawHtml, $crawler);

        $this->assertSame('title', array_values($textData->getFlatContent())[0]);
        $this->assertGreaterThan(10, $textData->getWordCount());
        $this->assertGreaterThan(10, $textData->getRatioTxtCode());
        // dump($textData->getTextAnalysis()->getExpressions(2));
        $this->assertArrayHasKey('web', $textData->getTextAnalysis()->getExpressions());
    }

    public function testHrefLangExtractor(): void
    {
        $rawHtml = $this->getPage('https://altimood.com/');

        $extractor = new HrefLangExtractor(new Crawler($rawHtml));
        $list = $extractor->getHrefLangList();

        $this->assertContains('https://altimood.com/en', $list);
    }
}
