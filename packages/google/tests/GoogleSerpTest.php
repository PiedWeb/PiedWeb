<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\Extractor\SERPExtractor;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleSERPManager;
use PiedWeb\Google\Puppeteer\PuppeteerConnector;

final class GoogleSerpTest extends TestCase
{
    private function getSerpManager(string $kw = 'pied web'): GoogleSERPManager
    {
        $manager = new GoogleSERPManager($kw, 'fr', 'fr-FR');
        $manager->generateGoogleSearchUrl();

        return $manager;
    }

    private function extractSERP(string $rawHtml, string $expectedFirstResult = 'https://piedweb.com/'): SERPExtractor
    {
        $extractor = new SERPExtractor($rawHtml);
        if ($expectedFirstResult !== $extractor->getResults()[0]->url) {
            $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
        }

        $this->assertNotEmpty($extractor->getResults()[0]->url);

        return $extractor;
    }

    public function testPuphpeteerMobile(): void
    {
        // requestGoogleWithCurl ➜ dead
        $rawHtml = (new GoogleRequester())->requestGoogleWithPuppeteer($this->getSerpManager());
        file_put_contents('./debug/debug-puphpeteer-mobile.html', $rawHtml);
        PuppeteerConnector::screenshot('./debug/debug-puphpeteer-mobile.png');

        $this->extractSERP($rawHtml);
    }

    public function testPuphpeteerMobileClickMoreResult(): void
    {
        $rawHtml = (new GoogleRequester())->requestGoogleWithPuppeteer($this->getSerpManager('iphone'));
        file_put_contents('./debug/debug-puphpeteer-mobile-more-results.html', $rawHtml);
        PuppeteerConnector::screenshot('./debug/debug-puphpeteer-mobile-more-results.png');

        $extractor = $this->extractSERP($rawHtml, 'https://www.apple.com/fr/iphone/');
        $resultsNbr = count($extractor->getResults());
        $this->assertGreaterThanOrEqual(20, $resultsNbr, $resultsNbr.' results found');
    }

    public function testCurlMobile(): void
    {
        $extractor = $this->getExtractor('Pied Web');
        $this->assertSame('https://piedweb.com/', $extractor->getResults()[0]->url);

        $extractor = $this->getExtractor('pied vert');
        $this->assertSame('https://piedvert.com/', $extractor->getResults()[0]->url);
    }

    private function getExtractor(string $query): SERPExtractor
    {
        $rawHtml = (new GoogleRequester())->requestGoogleWithPuppeteer($this->getSerpManager($query), maxPages: 1);
        file_put_contents('debug.html', $rawHtml);

        return new SERPExtractor($rawHtml);
    }

    public function testExtractionPositionZero(): void
    {
        // This test is not working anymore
        // Google deleted position zero on smartphone ???
        // TODO : change test for ➜ https://www.google.fr/search?q=steve+jobs+date+de+naissance

        $extractor = $this->getExtractor("qu'est ce que l'effet streisand");
        if (! $extractor->containsSerpFeature('PositionZero')) {
            $this->assertStringContainsString('wikipedia.org',  $extractor->getResults()[0]->url);
            dump('Position Zero was not checked');

            return;
        }

        $this->assertTrue($extractor->containsSerpFeature('PositionZero'));
        $this->assertStringContainsString('ligue-enseignement.be', $extractor->getPositionsZero()->url);
    }

    public function testExtractMaps(): void
    {
        foreach (['plombier champsaur', 'pied web consultant'] as $kw) {
            $extractor = $this->getExtractor($kw);
            file_put_contents('./debug/debugExtractMaps - '.$kw.'.html', $extractor->html);
            $mapsResults = $extractor->extractBusinessResults();
            dump($mapsResults[0] ?? null);
            $this->assertArrayHasKey(0, $mapsResults, $kw);
        }
    }

    public function testRelatedSearches(): void
    {
        $extractor = $this->getExtractor('randonnée valgaudemar');
        $relatedSearches = $extractor->getRelatedSearches();
        $this->assertContains('Rando Valgaudemar 3 jours', $relatedSearches);
    }
}
