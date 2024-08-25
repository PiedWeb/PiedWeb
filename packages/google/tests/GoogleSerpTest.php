<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\Extractor\SERPExtractor;
use PiedWeb\Google\Extractor\SERPExtractorJsExtended;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleSERPManager;

final class GoogleSerpTest extends TestCase
{
    private function getSerpManager(string $kw = 'pied web'): GoogleSERPManager
    {
        $manager = new GoogleSERPManager();
        $manager->language = 'fr-FR';
        $manager->tld = 'fr';
        $manager->q = $kw;
        $manager->parameters['hl'] = 'fr';
        $manager->generateGoogleSearchUrl();

        return $manager;
    }

    private function extractSERP(string $rawHtml, string $expectedFirstResult = 'https://piedweb.com/'): SERPExtractor
    {
        $extractor = new SERPExtractor($rawHtml);
        // $this->assertNotSame(0, $extractor->getNbrResults());
        if ($expectedFirstResult !== $extractor->getResults()[0]->url) {
            $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
        }

        $this->assertTrue(true);

        return $extractor;
    }

    public function testPuphpeteerMobile(): void
    {
        $manager = $this->getSerpManager();

        $googleRequester = new GoogleRequester();
        $rawHtml = $googleRequester->requestGoogleWithPuppeteer($manager); // $manager->getCache() ?? $manager->setCache($googleRequester->requestGoogleWithPuppeteer($manager));
        file_put_contents('./debug/debug-puphpeteer-mobile.html', $rawHtml);
        $googleRequester->getPuppeteerClient()->getBrowserPage()->screenshot(['path' => './debug/debug-puphpeteer-mobile.png']);

        $this->extractSERP($rawHtml);
    }

    public function testPuphpeteerMobileClickMoreResult(): void
    {
        $manager = $this->getSerpManager('iphone');

        $googleRequester = new GoogleRequester();
        $rawHtml = $googleRequester->requestGoogleWithPuppeteer($manager); // $manager->getCache() ?? $manager->setCache($googleRequester->requestGoogleWithPuppeteer($manager));
        file_put_contents('./debug/debug-puphpeteer-mobile-more-results.html', $rawHtml);
        $googleRequester->getPuppeteerClient()->getBrowserPage()->screenshot([
            'path' => './debug/debug-puphpeteer-mobile-more-results.png',
            // 'fullPage' => true,
        ]);

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

    private function getExtractor(string $query): SERPExtractorJsExtended
    {
        $manager = $this->getSerpManager($query);

        $googleRequester = new GoogleRequester();
        $rawHtml = $manager->getCache() ?? $manager->setCache((new GoogleRequester())->requestGoogleWithCurl($manager));
        file_put_contents('debug.html', $rawHtml);

        return new SERPExtractorJsExtended($rawHtml);
    }

    public function testExtractionPositionZero(): void
    {
        // This test is not working anymore
        // Google deleted position zero on smartphone ???

        $extractor = $this->getExtractor('liste meilleures randonnées alpes');

        $extractor->getBrowserPage()->screenshot(['path' => './debug/debug-position-zero.png']);
        if (! $extractor->containsSerpFeature('PositionZero')) {
            $this->assertStringContainsString('generationvoyage.fr',  $extractor->getResults()[0]->url);
            dump('Position Zero was not checked');

            return;
        }

        $this->assertTrue($extractor->containsSerpFeature('PositionZero'));
        $this->assertStringContainsString('generationvoyage.fr', $extractor->getPositionsZero()->url);
    }

    public function testExtractMaps(): void
    {
        // 'lac bleu valgaudemar altitude',
        // 'plombier paris'
        foreach (['altimood', 'accompagnateur montagne'] as $kw) {
            $extractor = $this->getExtractor($kw);

            $extractor->getBrowserPage()->screenshot(['path' => './debug/debugExtractMaps - '.$kw.'.png', 'fullPage' => true]);
            file_put_contents('./debug/debugExtractMaps - '.$kw.'.html', $extractor->getBrowserPage()->content());

            $mapsResults = $extractor->extractBusinessResults();
            dump($mapsResults[0] ?? null);
            $this->assertArrayHasKey(0, $mapsResults, $kw);
        }
    }

    public function testRelatedSearches(): void
    {
        $extractor = $this->getExtractor('randonnée valgaudemar');

        $extractor->getBrowserPage()->screenshot(['path' => './debug/debug-relatedSearches.png']);

        $relatedSearches = $extractor->getRelatedSearches();
        $this->assertContains('Rando Valgaudemar 3 jours', $relatedSearches);
    }
}
