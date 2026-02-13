<?php

namespace PiedWeb\Google\Extractor;

use PiedWeb\Extractor\Helper;
use PiedWeb\Google\Result\BusinessResult;
use PiedWeb\Google\Result\SearchResult;
use Symfony\Component\DomCrawler\Crawler;

class SERPExtractor
{
    /** @var array<string, list<string>> */
    final public const array SERP_FEATURE_SELECTORS = [
        'Ads' => ['.//*[@id="tads"]|.//*[@id="bottomads"]'],
        'ImagePack' => ["//span[text()='Images']", "//h3[starts-with(text(), 'Images correspondant')]"],
        'Local Pack' => [
            '//*[@data-viewer-entrypoint]',
            "//a[contains(@href,'/maps/dir/')]",
            "//div[contains(@class,'rllt__details')]",
            "//div[contains(@class,'VkpGBb')]",
            "//*[@role='heading'][text()='Adresses' or text()='Entreprises' or text()='Lieux' or text()='Places']",
        ],
        'PositionZero' => ['div[data-md="471"]'],
        'KnowledgePanel' => ['//div[contains(concat(" ",normalize-space(@class)," ")," kp-wholepage ")]'],
        'News' => ['//span[text()="À la une"]', '//span[text()="Top stories"]'],
        'PeopleAlsoAsked' => [
            '//span[text()="Autres questions posées"]',
            '//span[text()="People also ask"]',
        ],
        'Video' => ['//span[text()="Vidéos"]', '//span[text()="Videos"]', '//div[contains(@aria-label,"second")]'],
        'Reviews' => ['//span[contains(@aria-label,"Note")]', '//span[contains(@aria-label,"Rating")]', '//span[contains(@aria-label,"Rated")]'],
    ];

    /** @var string[] */
    final public const array RELATED = [
        '//span[text()="Recherches associées"]/ancestor::*[position() <  5]//a',
        '//span[text()="Related searches"]/ancestor::*[position() <  5]//a',
        '//span[text()="Search for next"]/ancestor::*[position() <  5]//a',
        '//span[text()="People also search for"]/ancestor::*[position() <  5]//a',
        "//a[@data-xbu][starts-with(@href, '/search')]/div",
    ];

    // public const RESULT_SELECTOR = '//a[@role="presentation"]/parent::div/parent::div/parent::div';
    final public const string RESULT_SELECTOR = "(//h2[text()='Extrait optimisé sur le Web' or text()='Featured snippet from the web']/ancestor::block-component//a[@class])[1]|//a[@role='presentation']|//div[@data-md=\"471\"]//a";

    private readonly Crawler $domCrawler;

    /**
     * @var SearchResult[]|null
     */
    private ?array $results = null;

    public function __construct(
        public string $html,
        private int $extractedAt = 0,
        private readonly string $wsEndpoint = ''
    ) {
        $this->domCrawler = new Crawler($html);
        $this->extractedAt = 0 === $this->extractedAt ? (int) (new \DateTime('now'))->format('ymdHi') : $this->extractedAt;
    }

    public function getNbrResults(): int
    {
        $node = null;
        if (! $this->exists(['//*[@id="resultStats"]|', '//*[@id="result-stats"]'], $node)) {
            return 0;
        }

        return (int) Helper::preg_replace_str('/[^0-9]/', '', $node->nodeValue ?? '');
    }

    /**
     * @return string[]
     */
    public function getAlsoAsked(): array
    {
        $alsoAsked = [];
        $nodes = $this->domCrawler->filterXpath('//div[@data-q]');
        foreach ($nodes as $node) {
            $alsoAsked[] = $node instanceof \DOMElement ? $node->getAttribute('data-q')
                : throw new \Exception();
        }

        return $alsoAsked;
    }

    /** CSS selectors tried in order to find business names in the local pack. */
    private const array BUSINESS_NAME_SELECTORS = [
        '.dbg0pd',
        '.rllt__details',
        '.VkpGBb [role="heading"]',
    ];

    /**
     * @return BusinessResult[]
     *
     * @psalm-suppress InvalidArrayOffset
     */
    public function extractBusinessResults(): array
    {
        $names = $this->extractBusinessNames();
        if ([] === $names) {
            return $this->extractLocalServiceResults();
        }

        $results = [];
        foreach ($names as $i => $name) {
            ['mid' => $mid, 'cid' => $cid] = $this->findIdentifiersNear($name);

            $results[] = new BusinessResult(
                cid: $cid,
                mid: $mid,
                name: $name,
                organicPos: $i + 1,
                position: $i + 1,
            );
        }

        return $results;
    }

    /**
     * Try multiple CSS selectors to find business names, deduplicated.
     *
     * @return string[]
     */
    private function extractBusinessNames(): array
    {
        foreach (self::BUSINESS_NAME_SELECTORS as $selector) {
            $nodes = $this->domCrawler->filter($selector);
            if (0 === $nodes->count()) {
                continue;
            }

            $names = [];
            $seen = [];
            foreach ($nodes as $node) {
                if (! $node instanceof \DOMElement) {
                    continue;
                }

                $name = $this->extractNameFromNode($node, $selector);
                if ('' === $name) {
                    continue;
                }
                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $names[] = $name;
            }

            if ([] !== $names) {
                return $names;
            }
        }

        return [];
    }

    private function extractNameFromNode(\DOMElement $node, string $usedSelector): string
    {
        // .dbg0pd and headings contain just the name
        if (! str_contains($usedSelector, 'rllt__details')) {
            return trim($node->textContent);
        }

        // .rllt__details contains name + rating + address; extract from .dbg0pd child or first text
        $inner = new Crawler($node);
        $nameNode = $inner->filter('.dbg0pd')->getNode(0);
        if ($nameNode instanceof \DOMElement) {
            return trim($nameNode->textContent);
        }

        $firstChild = $node->firstChild;
        if ($firstChild instanceof \DOMText || $firstChild instanceof \DOMElement) {
            $candidate = trim($firstChild->textContent);
            if ('' !== $candidate) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Search the raw HTML near a business name to find its mid (/g/...) and CID (0x...:0x...).
     *
     * @return array{mid: string, cid: string}
     */
    private function findIdentifiersNear(string $name): array
    {
        $mid = '';
        $cid = '';
        $pos = 0;

        while (false !== ($pos = strpos($this->html, $name, $pos))) {
            $start = max(0, $pos - 1000);
            $window = substr($this->html, $start, 2000 + \strlen($name));

            if ('' === $mid && 1 === preg_match('#/g/[\w\d]+#', $window, $m)) {
                $mid = $m[0];
            }

            if ('' === $cid && 1 === preg_match('/0x[0-9a-f]+:0x([0-9a-f]+)/', $window, $m)) {
                $cid = $this->hexCidToNumeric($m[1]);
            }

            if ('' !== $mid && '' !== $cid) {
                break;
            }

            ++$pos;
        }

        return ['mid' => $mid, 'cid' => $cid];
    }

    /**
     * Convert the entity part of a Google hex Place ID to a numeric CID.
     */
    private function hexCidToNumeric(string $hex): string
    {
        if (\function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16));
        }

        // bcmath fallback: convert hex to decimal digit by digit
        $dec = '0';
        foreach (str_split($hex) as $digit) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($digit));
        }

        return $dec;
    }

    /**
     * Fallback for local service ads / knowledge panel business cards.
     *
     * @return BusinessResult[]
     */
    private function extractLocalServiceResults(): array
    {
        $nodes = $this->domCrawler->filter('[data-prvwid="HEADER"] [role="heading"]');
        $results = [];
        $i = 0;
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $name = trim(Helper::htmlToPlainText($node->textContent));
            if ('' === $name) {
                continue;
            }

            if (\in_array($name, ['Partager', 'Share', 'Suggérer une modification', 'Suggest an edit'], true)) {
                continue;
            }

            ['mid' => $mid, 'cid' => $cid] = $this->findIdentifiersNear($name);

            $results[$i] = new BusinessResult(
                cid: $cid,
                mid: $mid,
                name: $name,
                organicPos: $i + 1,
                position: $i + 1,
                pixelPos: $this->getPixelPosFor($node->getNodePath() ?? ''),
            );
            ++$i;
        }

        return $results;
    }

    /**
     * @return SearchResult[]
     */
    public function getResults(bool $organicOnly = true): array
    {
        if (false === $organicOnly && null !== $this->results) {
            return $this->results;
        }

        $xpath = self::RESULT_SELECTOR;
        $nodes = $this->domCrawler->filterXpath($xpath);
        $toReturn = [];

        $i = 0;
        $iOrganic = 0;

        foreach ($nodes as $k => $node) {
            $ads = null !== $nodes->eq($k)->closest('#tads, #bottomads');
            if ($organicOnly && $ads) {
                continue;
            }

            $result = $this->extractResultFrom($node, $ads ? 0 : $iOrganic + 1, $i + 1, $ads);
            if (! $result instanceof SearchResult) {
                continue;
            }

            $toReturn[$i] = $result;
            ++$i;
            if (! $ads) {
                ++$iOrganic;
            }
        }

        if (false === $organicOnly) {
            $this->results = $toReturn;
        }

        return $toReturn;
    }

    private function extractResultFrom(\DOMNode $linkNode, int $organicPos, int $position, bool $ads = false): ?SearchResult
    {
        // $domCrawler = new Crawler($node);
        // $linkNode = $domCrawler->filter('a')->getNode(0);
        if (! $linkNode instanceof \DOMElement) {
            throw new \Exception('Google changes his selector.');
        }

        $href = $linkNode->getAttribute('href');
        // skip shopping Results
        if (str_starts_with($href, 'https://www.google.')) {
            return null;
        }

        // skip google image links (see in pos0)
        if (str_starts_with($href, '/search?')) {
            return null;
        }

        if (str_starts_with($href, '/aclk?')) {
            return null;
        }

        if (str_starts_with($href, '/interstitial?url=')) {
            $href = substr($href, \strlen('/interstitial?url='));
        }

        $toReturn = new SearchResult(
            organicPos: $organicPos,
            position: $position,
            url: $href,
            title: (new Crawler($linkNode))->text(''),
            pixelPos: $this->getPixelPosFor($linkNode->getNodePath() ?? ''),
            ads : $ads
        );

        return $toReturn;
    }

    protected function getPixelPosFor(?string $xpath): int
    {
        if ('' === $this->wsEndpoint) {
            return 0;
        }

        if (\in_array($xpath, ['', null], true)) {
            return 0;
        }

        $cmd = 'PUPPETEER_WS_ENDPOINT='.escapeshellarg($this->wsEndpoint).' '
            .'node '.escapeshellarg(__DIR__.'/../Puppeteer/pixelPos.js').' '.escapeshellarg($xpath);
        \Safe\exec($cmd, $output);

        /** @var string */
        $pixelPos = $output[0] ?? throw new \Exception();

        return (int) $pixelPos;
    }

    public function containsSerpFeature(string $serpFeatureName, int &$pos = 0): bool
    {
        $xpaths = self::SERP_FEATURE_SELECTORS[$serpFeatureName];
        if (! $this->exists($xpaths)) {
            return false;
        }

        $pos = $this->getPixelPosFor($this->getNode($xpaths)->getNodePath());

        return true;
    }

    /**
     * @return array<string, int>
     */
    public function getSerpFeatures(): array
    {
        $pos = 0;
        $result = [];
        foreach (array_keys(self::SERP_FEATURE_SELECTORS) as $serpFeatureName) {
            if ($this->containsSerpFeature($serpFeatureName, $pos)) {
                $result[$serpFeatureName] = $pos;
            }
        }

        return $result;
    }

    public function getPositionsZero(): SearchResult
    {
        $linkNodePositionZero = $this->domCrawler
            ->filter('div[data-md="471"] a')
            ->getNode(0);

        if (! $linkNodePositionZero instanceof \DOMNode || ! $linkNodePositionZero instanceof \DOMElement) {
            file_put_contents('./debug/debug-position-zero.html', $this->html);

            throw new \LogicException('Google has changed its selector (position Zero)');
        }

        $toReturn = new SearchResult(1, 1, $linkNodePositionZero->getAttribute('href'), $linkNodePositionZero->textContent, pixelPos: $this->getPixelPosFor($linkNodePositionZero->getNodePath() ?? ''));

        return $toReturn;
    }

    /**
     * @return string[]
     */
    public function getRelatedSearches(): array
    {
        $kw = [];
        $xpaths = self::RELATED;
        foreach ($xpaths as $xpath) {
            $nodes = $this->domCrawler->filterXPath($xpath);
            foreach ($nodes as $node) {
                if ('' !== $node->textContent) {
                    $kw[] = trim($node->textContent);
                }
            }
        }

        return $kw;
    }

    /**
     * @param string[] $xpaths
     */
    public function exists(array $xpaths, ?\DOMNode &$node = null): bool
    {
        try {
            $node = $this->getNode($xpaths);

            return true;
        } catch (\LogicException) {
            return false;
        }
    }

    /**
     * @param string[] $xpaths
     */
    public function getNode(array $xpaths): \DOMNode
    {
        foreach ($xpaths as $xpath) {
            $node = $this->domCrawler->filterXPath($xpath)->getNode(0);
            if (! $node instanceof \DOMNode) {
                continue;
            }

            if ('' === $node->nodeValue) {
                continue;
            }

            return $node;
        }

        throw new \LogicException('`'.implode('`, ', $xpaths).'` not found');
    }

    public function toJson(): string
    {
        return \Safe\json_encode([
            'version' => '1',
            'extractedAt' => $this->extractedAt,
            'resultStat' => $this->getNbrResults(),
            'serpFeatures' => $this->getSerpFeatures(),
            'relatedSearches' => $this->getRelatedSearches(),
            'results' => $this->getResults(false),
            'alsoAsked' => $this->getAlsoAsked(),
            'businessResults' => $this->extractBusinessResults(),
        ]);
    }
}
