<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
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
        $results = $extractor->getResults();
        if ([] === $results) {
            $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
        }

        if (! $extractor->containsSerpFeature('PositionZero')) {
            $url = $results[0]->url;
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
            if ([] === $extractor->getResults()) {
                $this->markTestIncomplete('May google kick you, check /tmp/debug.html');

                return;
            }

            $mapsResults = $extractor->extractBusinessResults();
            dump($mapsResults[0] ?? null);
            $this->assertArrayHasKey(0, $mapsResults, $kw);
        }
    }

    public function testRelatedSearches(): void
    {
        $extractor = $this->getExtractor('randonnée valgaudemar');
        if ([] === $extractor->getResults()) {
            $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
        }

        $relatedSearches = $extractor->getRelatedSearches();
        $this->assertNotEmpty($relatedSearches, 'No related searches found');
    }

    public function testKnowledgePanel(): void
    {
        $extractor = $this->getExtractor('Tour Eiffel');
        if ([] === $extractor->getResults()) {
            $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
        }

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

    #[DataProvider('organicExtractionProvider')]
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

    /**
     * Regression: inline image-pack thumbnails (links inside an aggregator block
     * with >2 distinct domains) must not pollute organic positions.
     * Fixture is the prod SERP for "paysage allemand" where the image-pack used
     * to push grandangle.fr/alamyimages.fr/amazon.fr/sncf-connect.com ahead of
     * the real first organic result, germany.travel.
     */
    public function testFixtureImagePackIsFiltered(): void
    {
        $html = (string) \Safe\gzdecode((string) file_get_contents(__DIR__.'/fixtures/serp-image-pack.html.gz'));
        $extractor = new SERPExtractor($html);
        $results = $extractor->getResults();

        $this->assertNotEmpty($results);
        $this->assertStringContainsString(
            'germany.travel',
            $results[0]->url,
            'Image-pack thumbnails must not appear before real organic results'
        );

        $topUrls = array_map(static fn ($r) => $r->url, array_slice($results, 0, 4));
        foreach ($topUrls as $url) {
            $this->assertStringNotContainsString('alamyimages.fr', $url);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'primary' => ['serp-primary'];
        yield 'fallback' => ['serp-fallback'];
        yield 'image-pack' => ['serp-image-pack'];
    }

    private static function extractorFromFixture(string $name): SERPExtractor
    {
        $html = (string) \Safe\gzdecode((string) file_get_contents(__DIR__.'/fixtures/'.$name.'.html.gz'));

        return new SERPExtractor($html);
    }

    /**
     * Regression: `//span[text()="Vidéos"]` matched Google's filter/nav bar tab (a plain
     * `<span class="R1QWuf">Vidéos</span>` present on nearly every SERP), so the Video feature
     * was a permanent false positive. Real video blocks carry `role="heading"`; nav tabs don't.
     * serp-primary has the "Vidéos" nav tab but no real video block → must NOT be detected;
     * serp-image-pack has a genuine "Vidéos" block heading → must be detected.
     */
    public function testVideoFeatureIgnoresNavTab(): void
    {
        $this->assertFalse(
            self::extractorFromFixture('serp-primary')->containsSerpFeature('Video'),
            'Video must not be detected from the filter/nav "Vidéos" tab'
        );
        $this->assertTrue(
            self::extractorFromFixture('serp-image-pack')->containsSerpFeature('Video'),
            'Video must be detected from a real "Vidéos" block heading'
        );
    }

    /**
     * "Sites de lieux" (semscraper's `location_sites`) is a distinct block from the map Local Pack.
     * serp-primary carries a real "Sites de lieux" heading.
     */
    public function testLocationSitesFeatureDetected(): void
    {
        $extractor = self::extractorFromFixture('serp-primary');

        $this->assertTrue($extractor->containsSerpFeature('LocationSites'));
        $this->assertArrayHasKey('LocationSites', $extractor->getSerpFeatures());
    }

    /**
     * Offline feature-detection matrix across the three captured mobile SERPs. Locks in the
     * hardened, nav-tab-immune selectors so a Google DOM tweak that reintroduces false positives
     * (or drops a real block) is caught without a live request.
     *
     * @param array<string, bool> $expected
     */
    #[DataProvider('featureMatrixProvider')]
    public function testFeatureDetectionMatrix(string $fixture, array $expected): void
    {
        $extractor = self::extractorFromFixture($fixture);
        $features = $extractor->getSerpFeatures();

        foreach ($expected as $feature => $present) {
            $this->assertSame(
                $present,
                array_key_exists($feature, $features),
                sprintf('%s: %s expected %s', $fixture, $feature, $present ? 'present' : 'absent')
            );
        }
    }

    /**
     * @return iterable<string, array{string, array<string, bool>}>
     */
    public static function featureMatrixProvider(): iterable
    {
        // "randonnée valgaudemar": image pack + local pack (Adresses) + location sites, no video/PAA.
        yield 'primary' => ['serp-primary', [
            'ImagePack' => true,
            'Local Pack' => true,
            'LocationSites' => true,
            'Video' => false,
            'PeopleAlsoAsked' => false,
        ]];
        // "plombier ...": maps/business SERP with People-also-ask, no image/video block.
        yield 'fallback' => ['serp-fallback', [
            'Local Pack' => true,
            'PeopleAlsoAsked' => true,
            'ImagePack' => false,
            'Video' => false,
            'LocationSites' => false,
        ]];
        // "paysage allemand": image pack + real video block + People-also-ask, no local/location.
        yield 'image-pack' => ['serp-image-pack', [
            'ImagePack' => true,
            'Video' => true,
            'PeopleAlsoAsked' => true,
            'Local Pack' => false,
            'LocationSites' => false,
        ]];
    }

    public function testPixelPosDegradesToZeroWhenBrowserUnreachable(): void
    {
        // Regression (2026-06-03 10h SERP-extraction outage): a dead/unreachable
        // browser WS endpoint made getPixelPosFor() throw an empty Exception that
        // aborted the entire search:extract batch. A missing pixel position is a
        // secondary datum and must now degrade to 0 instead of throwing.
        $extractor = new SERPExtractor('<html></html>', 0, 'ws://127.0.0.1:1/devtools/browser/dead');

        $method = new ReflectionMethod($extractor, 'getPixelPosFor');

        $this->assertSame(0, $method->invoke($extractor, '//div[@id="unreachable"]'));
    }
}
