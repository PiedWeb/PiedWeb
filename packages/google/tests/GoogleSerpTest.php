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
            if (str_starts_with($url, '/goto?url=')) {
                $this->markTestIncomplete('Google returned an opaque redirect that the consuming app resolves over HTTP');
            }
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
                // markTestIncomplete() throws, so it ends the test on its own.
                $this->markTestIncomplete('May google kick you, check /tmp/debug.html');
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

    public function testGotoWithSpecificInlineDestinationIsResolvedAndMarked(): void
    {
        $html = '<html><body><script>[&quot;https://example.com/specific-page&quot;,&quot;opaque-token&quot;]</script>'
            .'<div><div><div><a role="presentation" href="/goto?url=opaque-token"><h3>Title</h3></a></div></div></div>'
            .'</body></html>';
        $extractor = new SERPExtractor($html);
        $result = $extractor->getResults(false)[0];

        $this->assertSame('https://example.com/specific-page', $result->url);
        $this->assertTrue($result->wasGotoWrapped());
        $this->assertTrue($result->wasGotoResolvedInline());

        /** @var array{results:list<array<string, mixed>>} $json */
        $json = json_decode($extractor->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['organicPos', 'position', 'url', 'title', 'description', 'pixelPos', 'ads'],
            array_keys($json['results'][0]),
            'goto provenance is internal metadata, not part of the import contract',
        );
    }

    public function testGotoWithOnlyAnInlineDomainKeepsTheWrapperForHttpResolution(): void
    {
        $html = '<html><body><script>[&quot;https://example.com/&quot;,&quot;opaque-token&quot;]</script>'
            .'<div><div><div><a role="presentation" href="/goto?url=opaque-token"><h3>Title</h3></a></div></div></div>'
            .'</body></html>';
        $result = (new SERPExtractor($html))->getResults(false)[0];

        $this->assertSame('/goto?url=opaque-token', $result->url);
        $this->assertTrue($result->wasGotoWrapped());
        $this->assertFalse($result->wasGotoResolvedInline());
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

    public function testPrimaryXpathFiltersAiOverviewSources(): void
    {
        $html = '<div id="rso">'
            .'<div><div data-aim="1"><a role="presentation" href="https://www.instagram.com/reel/example">'
            .'<h3>AI Overview source</h3></a></div></div>'
            .'<div><a role="presentation" href="https://www.ecrins-parcnational.fr/page"><h3>Organic result</h3></a></div>'
            .'</div>';
        $extractor = new SERPExtractor($html);
        $results = $extractor->getResults();

        $this->assertSame('xpath', $extractor->getLastExtractionMethod());
        $this->assertCount(1, $results);
        $this->assertSame('https://www.ecrins-parcnational.fr/page', $results[0]->url);
        $this->assertSame(1, $results[0]->organicPos);
    }

    public function testPrimaryXpathFiltersImagePackLinks(): void
    {
        $html = '<div id="rso">'
            .'<div><div role="heading">Images</div>'
            .'<a role="presentation" href="https://contents.mediadecathlon.com/picture.jpg"><h3>Image one</h3></a>'
            .'<a role="presentation" href="https://www.grimper.com/poutre.jpg"><h3>Image two</h3></a></div>'
            .'<div><a role="presentation" href="https://www.approche-escalade.fr/entrainement"><h3>Organic result</h3></a></div>'
            .'</div>';
        $extractor = new SERPExtractor($html);
        $results = $extractor->getResults();

        $this->assertSame('xpath', $extractor->getLastExtractionMethod());
        $this->assertCount(1, $results);
        $this->assertSame('https://www.approche-escalade.fr/entrainement', $results[0]->url);
        $this->assertSame(1, $results[0]->organicPos);
    }

    /**
     * Regression: the Local Pack ("Entreprises"/"Adresses"/"Lieux") renders one plain external
     * "Site Web" link per business card. Fixture is the prod SERP for "taxi névache", where those
     * map links took organic positions 1 and 2 (facebook.com/NAVIGHALP, taximev.fr) and pushed the
     * real first organic result, hautesvallees.com, down to 3.
     */
    public function testFixtureLocalPackIsFiltered(): void
    {
        $extractor = self::extractorFromFixture('serp-local-pack');
        $results = $extractor->getResults();

        $this->assertTrue($extractor->containsSerpFeature('Local Pack'), 'Fixture must carry a Local Pack');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString(
            'hautesvallees.com',
            $results[0]->url,
            'Local Pack business links must not appear before real organic results'
        );

        foreach ($results as $result) {
            $this->assertStringNotContainsString('facebook.com/NAVIGHALP', $result->url);
            $this->assertStringNotContainsString('taximev.fr', $result->url);
        }

        // The businesses themselves stay available through the dedicated map extraction.
        $businesses = array_map(static fn ($b) => $b->name, $extractor->extractBusinessResults());
        $this->assertContains('VTC NAVIGHALP', $businesses);
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

    public function testAlsoAskedIgnoresUnrelatedDataQAttributes(): void
    {
        $feedbackOnly = new SERPExtractor('<div class="SvjEff" data-q="tour du mont blanc">Feedback</div>'
            .'<div class="vqSUyf ECOb7c" data-q="0">Survey</div>');

        $this->assertSame([], $feedbackOnly->getAlsoAsked());
        $this->assertFalse($feedbackOnly->containsSerpFeature('PeopleAlsoAsked'));

        $organicResult = (new SERPExtractor('<div id="rso"><div>'.$feedbackOnly->html
            .'<a ping="" href="https://example.com/page"><h3>Organic result</h3></a>'
            .'</div></div>'))->getResults();

        $this->assertSame('https://example.com/page', $organicResult[0]->url);

        $withQuestion = new SERPExtractor($feedbackOnly->html
            .'<div class="related-question-pair" data-q="   ">Blank</div>'
            .'<div class="related-question-pair" data-q="Qui ?">Too short</div>'
            .'<div class="related-question-pair" data-q="Pourquoi ?">Ten characters</div>'
            .'<div class="related-question-pair" data-q="Où aller ?">Ten multibyte characters</div>'
            .'<div class="related-question-pair" data-q="12345678901">Numeric</div>'
            .'<div class="wQiwMc related-question-pair" data-q="Quel prix ?">Question</div>');

        $this->assertSame(['Quel prix ?'], $withQuestion->getAlsoAsked());
        $this->assertTrue($withQuestion->containsSerpFeature('PeopleAlsoAsked'));
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
     * AI Overview (SGE): the direct/Chrome lane must detect it under the same `ai_overview` key the
     * semscraper lane emits, so a keyword scraped via either lane yields the same feature. Captured in
     * its async "search_in_progress" placeholder state (the widget streams in after page load).
     */
    public function testAiOverviewFeatureDetected(): void
    {
        $extractor = self::extractorFromFixture('serp-ai-overview');

        $this->assertTrue($extractor->containsSerpFeature('ai_overview'), 'AI Overview block not detected');
        $this->assertArrayHasKey('ai_overview', $extractor->getSerpFeatures());
    }

    /**
     * The citations of a *rendered* AI Overview — serp-ai-overview was captured while the widget was
     * still saying "An AI Overview is not available for this search", which is a real state but not
     * the interesting one. serp-ai-overview-cited ("arolla rando zermatt", mobile FR) carries the
     * finished widget: 22 links inside the container for 7 distinct cited pages, each appearing in
     * the sources panel and again in its hover card, one of them also quoted in the text.
     */
    public function testAiOverviewCitationsAreExtractedAndDeduplicated(): void
    {
        $citations = self::extractorFromFixture('serp-ai-overview-cited')->getAiOverviewCitations();

        $this->assertCount(6, $citations, 'the same page listed in the panel and its hover card is one citation');
        $this->assertSame(
            [
                'https://www.alta-via.fr/fr/rando-glaciaire-haute-route-arolla-zermatt',
                'https://www.odyssee-montagne.fr/arolla-zermatt.html',
                'https://www.chamonix-zermatt.fr/fr/arolla-zermatt-ski',
                'https://www.kazaden.com/sp-ski-de-randonnee/ac-traversee-arolla-zermatt-2108',
                'https://www.alltrails.com/fr/randonnee/switzerland/valais/walkers-haute-route-osten-arolla-zermatt',
                'https://www.decathlon.fr/p/mp/randonnee-en-liberte-d-arolla-a-zermatt/f8ff23af-8ce8-41c3-9dcf-c02f06ae1b53/novar',
            ],
            array_column($citations, 'url'),
            'reading order: the in-text citation comes before the sources panel'
        );

        // Ranks are dense and 1-based, and Google's chrome (support/policies links, share buttons)
        // must not have taken a slot.
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($citations, 'pos'));

        // alta-via is quoted inside the generated answer (an <a> inside a <mark>); the others are
        // only listed as sources.
        $this->assertTrue($citations[0]['citedInText']);
        $this->assertSame([false, false, false, false, false], array_column(array_slice($citations, 1), 'citedInText'));

        // The brand is the panel card's own label, even for the page first seen as an in-text
        // citation — there, the link text is the quoted phrase, not a site name.
        $this->assertSame('www.alta-via.fr', $citations[0]['brand']);
        $this->assertSame('Odyssée Montagne', $citations[1]['brand']);
        $this->assertSame('Decathlon', $citations[5]['brand']);
    }

    /** A SERP whose AI Overview never rendered has no citations, and says so without throwing. */
    public function testAiOverviewWithoutCitationsYieldsAnEmptyList(): void
    {
        $this->assertSame([], self::extractorFromFixture('serp-ai-overview')->getAiOverviewCitations());
        $this->assertSame([], self::extractorFromFixture('serp-primary')->getAiOverviewCitations());
    }

    /**
     * The other source-card shape, and the reason `brand` is not simply the card's first text:
     * serp-ai-overview-plain-cards ("rando champsaur valgo index") renders cards that lead with the
     * page title instead of the site label. It is the first SERP the feature met in production, and it
     * banked ten page titles as brands ("Balades et randonnées | Champsaur Valgaudemar – Parc National
     * des Ecrins"). A card whose first text is the link's own aria-label carries no brand, and an empty
     * brand is the honest answer — the host is stored either way.
     */
    public function testAiOverviewPlainSourceCardsCarryNoBrandRatherThanThePageTitle(): void
    {
        $citations = self::extractorFromFixture('serp-ai-overview-plain-cards')->getAiOverviewCitations();

        $this->assertCount(10, $citations);

        // The two in-text citations keep the quoted phrase; every plain card comes back brandless.
        $this->assertSame('Champsaur Valgaudemar', $citations[0]['brand']);
        $this->assertTrue($citations[0]['citedInText']);
        $this->assertSame('Les Randonneurs du Champsaur Valgaudemar', $citations[1]['brand']);
        $this->assertSame(
            ['', '', '', '', '', '', '', ''],
            array_column(array_slice($citations, 2), 'brand'),
            'a page title must never be stored as a brand'
        );
    }

    /**
     * toJson() is the contract the app imports through (SearchResultsImportJson reads `aiOverview`),
     * and semscraper's mapper reproduces this exact shape — so the key must be there, spelled this way,
     * with the fields the importer reads.
     */
    public function testToJsonCarriesTheAiOverviewCitations(): void
    {
        /** @var array<string, mixed> $json */
        $json = json_decode(self::extractorFromFixture('serp-ai-overview-cited')->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('aiOverview', $json);
        $citations = $json['aiOverview'];
        $this->assertIsArray($citations);
        $this->assertCount(6, $citations);
        $this->assertIsArray($citations[0]);
        $this->assertSame(['url', 'brand', 'pos', 'pixelPos', 'citedInText'], array_keys($citations[0]));
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
            'ai_overview' => false,
        ]];
        // "plombier ...": maps/business SERP with People-also-ask, no image/video block.
        yield 'fallback' => ['serp-fallback', [
            'Local Pack' => true,
            'PeopleAlsoAsked' => true,
            'ImagePack' => false,
            'Video' => false,
            'LocationSites' => false,
            'ai_overview' => false,
        ]];
        // "paysage allemand": image pack + real video block + People-also-ask, no local/location.
        yield 'image-pack' => ['serp-image-pack', [
            'ImagePack' => true,
            'Video' => true,
            'PeopleAlsoAsked' => true,
            'Local Pack' => false,
            'LocationSites' => false,
            'ai_overview' => false,
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
