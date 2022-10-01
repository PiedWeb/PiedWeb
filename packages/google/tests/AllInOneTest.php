<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\Extractor\SERPExtractor;
use PiedWeb\Google\Extractor\SERPExtractorJsExtended;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleSERPManager;

final class AllInOneTest extends TestCase
{
    private function getSerpManager()
    {
        $manager = new GoogleSERPManager();
        $manager->language = 'fr-FR';
        $manager->tld = 'fr';
        $manager->q = 'pied web';
        // $manager->setParameter('num', '100');
        $manager->generateGoogleSearchUrl();

        return $manager;
    }

    private function extractSERP(string $rawHtml)
    {
        $extractor = new SERPExtractor($rawHtml);
        // $this->assertNotSame(0, $extractor->getNbrResults());
        $this->assertSame('https://piedweb.com/', $extractor->getResults()[0]->url);
    }

    public function testPuphpeteerMobile(): void
    {
        $manager = $this->getSerpManager();

        $googleRequester = new GoogleRequester();
        $rawHtml = $manager->getCache() ?? $manager->setCache($googleRequester->requestGoogleWithPuppeteer($manager));
        file_put_contents('debug.html', $rawHtml);
        $googleRequester->getPuppeteerClient()->getBrowserPage()->screenshot(['path' => 'debug.png']);

        $this->extractSERP($rawHtml);
    }

    public function testCurlMobile(): void
    {
        $extractor = $this->getExtractor('Pied Web');
        $this->assertSame('https://piedweb.com/', $extractor->getResults()[0]->url);
    }

    private function getExtractor(string $query): SERPExtractorJsExtended
    {
        $manager = $this->getSerpManager();
        $manager->q = $query;

        $googleRequester = new GoogleRequester();
        $rawHtml = $manager->getCache() ?? $manager->setCache((new GoogleRequester())->requestGoogleWithCurl($manager));
        file_put_contents('debug.html', $rawHtml);

        return new SERPExtractorJsExtended($rawHtml);
    }

    public function testExtractionPositionZero()
    {
        $extractor = $this->getExtractor('marmotte vercors'); // position Zero PiedVert.com, if test failed, check position Zero on SERP exists

        $extractor->getBrowserPage()->screenshot(['path' => 'debug.png']);
        if (false === $extractor->containsSerpFeature('PositionZero')) {
            $this->assertStringContainsString('piedvert.com', 'https://piedweb.com/', $extractor->getResults()[0]->url);
            dump('Position Zero was not checked');

            return;
        }
        $this->assertTrue($extractor->containsSerpFeature('PositionZero'));
        $this->assertStringContainsString('piedvert.com', $extractor->getPositionsZero()->url);
    }
}
