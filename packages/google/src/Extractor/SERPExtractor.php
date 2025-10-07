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
        'Local Pack' => ["//div[text()='Adresses']", "//div[text()='Entreprises']"],
        'PositionZero' => ['div[data-md="471"]'],
        'KnowledgePanel' => ['//div[contains(concat(" ",normalize-space(@class)," ")," kp-wholepage ")]'],
        'News' => ['//span[text()="À la une"]'],
        'PeolpleAlsoAsked' => [
            '//span[text()="Autres questions posées"]',
            '//span[text()="People also ask"]',
        ],
        'Video' => ['//span[text()="Vidéos"]',            '//div[contains( @aria-label,"second")]'],
        'Reviews' => ['//span[contains( @aria-label,"Note")]'],
    ];

    /** @var string[] */
    final public const array RELATED = [
        '//span[text()="Recherches associées"]/ancestor::*[position() <  5]//a',
        '//span[text()="Search for next"]/ancestor::*[position() <  5]//a',
        '//span[text()="People also search for"]/ancestor::*[position() <  5]//a',
        "//a[@data-xbu][starts-with(@href, '/search')]/div",
    ];

    // public const RESULT_SELECTOR = '//a[@role="presentation"]/parent::div/parent::div/parent::div';
    final public const string RESULT_SELECTOR = "(//h2[text()='Extrait optimisé sur le Web']/ancestor::block-component//a[@class])[1]|//a[@role='presentation']|//div[@data-md=\"471\"]//a";

    // (//h2[text()='Extrait optimisé sur le Web']/ancestor::block-component//a[@class])[1]|//a[@role="presentation"]
    final public const string RESULT_SELECTOR_DESKTOP =
        '//a[not(starts-with(@href, "/search"))]/parent::div/parent::div/parent::div[@data-hveid]
        |//a[not(starts-with(@href, "/search"))]/parent::div/parent::div/parent::div[@data-sokoban-container]';

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

    private function isMobileSerp(): bool
    {
        return $this->exists([self::RESULT_SELECTOR]);
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

    /**
     * @return BusinessResult[]
     *
     * @psalm-suppress InvalidArrayOffset
     */
    public function extractBusinessResults(): array
    {
        $selector = 'a[data-open-viewer]';
        $nodes = $this->domCrawler->filter($selector);
        $mapsResults = [];
        $i = 0;
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $href = $node->getAttribute('href');
            if (1 !== preg_match('/mid=(\/g\/[\w\d]+)/', $href, $matches)) {
                continue; // TODO log it
            }

            $mid = $matches[1];

            $mapsResults[$i] = new BusinessResult(
                mid: $mid,
                name: (new Crawler($node))->filter('[role]')->first()->text(''),
                organicPos: $i + 1,
                position: $i + 1,
                pixelPos: $this->getPixelPosFor($node->getNodePath() ?? ''),
            ); // data-rc_ludocids

            ++$i;
        }

        if (\count($mapsResults) < 1) {
            return $this->extractLocalServiceResults();
        }

        return $mapsResults;
    }

    /**
     * @return BusinessResult[]
     */
    private function extractLocalServiceResults(): array
    {
        // [data-docid]
        $selector = '[data-prvwid="HEADER"] [role="heading"]';
        $nodes = $this->domCrawler->filter($selector);
        $mapsResults = [];
        $i = 0;
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            if ('' === $node->textContent) {
                continue;
            }

            $mapsResults[$i] = new BusinessResult(
                cid: $this->getCidFromLocalServiceResult(),
                mid: $this->getMidFromLocalServiceResult(),
                name: trim(Helper::htmlToPlainText($node->textContent)),
                organicPos: $i + 1,
                position: $i + 1,
                pixelPos: $this->getPixelPosFor($node->getNodePath() ?? '')
            );
            ++$i;
        }

        return $mapsResults;
    }

    private function getMidFromLocalServiceResult(): string
    {
        preg_match('/,"(\/g\/[\w\d]+)",/', $this->html, $matches);

        return $matches[1] ?? '';
    }

    private function getCidFromLocalServiceResult(): string
    {
        $node = $this->domCrawler->filter('[data-docid]')->getNode(0);
        if (! $node instanceof \DOMElement) {
            return '0';
        }

        return $node->getAttribute('data-docid');
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
            // skip if you are in ads
            $ads = null !== $nodes->eq($k)->closest('#tads, #bottomads');
            if ($organicOnly && $ads) {
                continue;
            }

            $result = $this->extractResultFrom($node, $ads ? 0 : $iOrganic + 1,  $i + 1, $ads);
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
            dump($xpath);
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
