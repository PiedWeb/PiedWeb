<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\Extractor\SERPExtractor;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleSERPManager;
use PiedWeb\Google\Puppeteer\PuppeteerConnector;

final class GoogleSerpTest extends TestCase
{
    private function getSerpManager(string $kw = 'pied web', string $tld = 'fr', string $language = 'fr-FR'): GoogleSERPManager
    {
        $manager = new GoogleSERPManager($kw, $tld, $language);
        $manager->generateGoogleSearchUrl();

        return $manager;
    }

    private function extractSERP(string $rawHtml, string $expectedFirstResult = 'https://piedweb.com/'): SERPExtractor
    {
        $extractor = new SERPExtractor($rawHtml);
        $results = $extractor->getResults();
        if ([] === $results) {
            $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
        }

        $this->assertNotEmpty($results[0]->url);

        if ('blocks' === $extractor->getLastExtractionMethod()) {
            dump('⚠ Primary RESULT_SELECTOR returned 0 results — structural fallback used. Google may have changed their SERP layout.');
        }

        if ($expectedFirstResult !== $results[0]->url) {
            dump('Expected first result: '.$expectedFirstResult.', got: '.$results[0]->url);
        }

        return $extractor;
    }

    public function testPuphpeteerMobile(): void
    {
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
        $this->assertGreaterThanOrEqual(15, $resultsNbr, $resultsNbr.' results found');
    }

    private function getExtractor(string $query, string $tld = 'fr', string $language = 'fr-FR'): SERPExtractor
    {
        $rawHtml = (new GoogleRequester())->requestGoogleWithPuppeteer($this->getSerpManager($query, $tld, $language), maxPages: 1);
        file_put_contents('./debug/debug-'.preg_replace('/[^a-z0-9]+/', '-', strtolower($query)).'.html', $rawHtml);

        return new SERPExtractor($rawHtml);
    }

    public function testExtractionPositionZero(): void
    {
        // This test is not working anymore
        // Google deleted position zero on smartphone ???
        // TODO : change test for ➜ https://www.google.fr/search?q=steve+jobs+date+de+naissance

        $extractor = $this->getExtractor("qu'est ce que l'effet streisand");
        if (! $extractor->containsSerpFeature('PositionZero')) {
            $url = $extractor->getResults()[0]->url;
            $this->assertMatchesRegularExpression('(wikipedia.org|ligue-enseignement.be)',  $url);
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
        $this->assertNotEmpty($relatedSearches, 'No related searches found');
    }

    public function testKnowledgePanel(): void
    {
        $extractor = $this->getExtractor('Tour Eiffel');
        $this->assertTrue($extractor->containsSerpFeature('KnowledgePanel'), 'KnowledgePanel not found');
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function organicExtractionProvider(): iterable
    {
        // French queries
        yield 'fr branded' => ['consultant seo montagne', 'fr', 'fr-FR', 3];
        yield 'fr informational' => ['comment faire du pain', 'fr', 'fr-FR', 5];
        yield 'fr local' => ['restaurant lyon', 'fr', 'fr-FR', 3];
        // English queries
        yield 'en branded' => ['stack overflow', 'com', 'en', 3];
        yield 'en informational' => ['how to make sourdough bread', 'com', 'en', 5];
        yield 'en local' => ['coffee shop london', 'com', 'en', 3];
    }

    /**
     * @dataProvider organicExtractionProvider
     */
    public function testOrganicExtraction(string $query, string $tld, string $language, int $minResults): void
    {
        $extractor = $this->getExtractor($query, $tld, $language);
        $results = $extractor->getResults();
        $method = $extractor->getLastExtractionMethod();

        if ([] === $results) {
            $this->markTestIncomplete("0 results for '{$query}' ({$tld}/{$language}) — Google may have blocked the request");
        }

        $this->assertGreaterThanOrEqual($minResults, count($results), "Too few results for '{$query}' ({$method} path)");
        $this->assertNotEmpty($results[0]->url, "Empty URL for first result of '{$query}'");
        $this->assertNotEmpty($results[0]->title, "Empty title for first result of '{$query}'");

        if ('blocks' === $method) {
            dump("⚠ '{$query}' ({$tld}): primary RESULT_SELECTOR returned 0 — structural fallback used");
        }
    }

    /**
     * Offline test: primary RESULT_SELECTOR extracts results from an old-layout SERP fixture.
     */
    public function testFixturePrimaryXpath(): void
    {
        $html = (string) \Safe\gzdecode((string) file_get_contents(__DIR__.'/fixtures/serp-primary.html.gz'));
        $extractor = new SERPExtractor($html);
        $results = $extractor->getResults();

        $this->assertNotEmpty($results, 'Primary xpath selector returned 0 results from fixture');
        $this->assertSame('xpath', $extractor->getLastExtractionMethod());
        $this->assertGreaterThanOrEqual(10, count($results));
    }

    /**
     * Offline test: structural fallback extracts results from a new-layout SERP fixture
     * where the primary RESULT_SELECTOR returns 0.
     */
    public function testFixtureStructuralFallback(): void
    {
        $html = (string) \Safe\gzdecode((string) file_get_contents(__DIR__.'/fixtures/serp-fallback.html.gz'));
        $extractor = new SERPExtractor($html);
        $results = $extractor->getResults();

        $this->assertNotEmpty($results, 'Structural fallback returned 0 results — block extraction is broken');
        $this->assertSame('blocks', $extractor->getLastExtractionMethod());
        $this->assertGreaterThanOrEqual(8, count($results));
        $this->assertStringContainsString('pagesjaunes.fr', $results[0]->url);
    }
}
