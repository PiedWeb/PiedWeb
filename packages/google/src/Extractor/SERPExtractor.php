<?php

namespace PiedWeb\Google\Extractor;

use LogicException;
use PiedWeb\Extractor\Helper;
use PiedWeb\Google\Result\SearchResult;
use Symfony\Component\DomCrawler\Crawler;

class SERPExtractor
{
    public const SERP_FEATURE_SELECTORS = [
        'Ads' => ['.//*[@id="tads"]|.//*[@id="bottomads"]'],
        'ImagePack' => ["//span[text()='Images']", "//h3[starts-with(text(), 'Images correspondant')]"],
        'Local Pack' => ["//div[text()='Adresses']"],
        'PositionZero' => ["//h2[text()='Extrait optimisé sur le Web']"],
        'KnowledgePanel' => ['//div[contains(concat(" ",normalize-space(@class)," ")," kp-wholepage ")]'],
        'News' => ['//span[text()="À la une"]'],
        'PeolpleAlsoAsked' => ['//span[text()="Autres questions posées"]'],
        'Video' => ['//span[text()="Vidéos"]',            '//div[contains( @aria-label,"second")]'],
        'Reviews' => ['//span[contains( @aria-label,"Note")]'],
    ];

    /**
     * @var string[]
     */
    public const RELATED = ["//a[@data-xbu][starts-with(@href, '/search')]/div/div/span"];

    /**
     * @var string[]
     */
    public const RELATED_DESKTOP = ["//a[@data-xbu][starts-with(@href, '/search')]/div"];

    /**
     * @var string
     */
    public const RESULT_SELECTOR = '//a[@role="presentation"]/parent::div/parent::div/parent::div';

    /**
     * @var string
     */
    public const RESULT_SELECTOR_DESKTOP =
        '//a[not(starts-with(@href, "/search"))]/parent::div/parent::div/parent::div[@data-hveid]
        |//a[not(starts-with(@href, "/search"))]/parent::div/parent::div/parent::div[@data-sokoban-container]';

    private Crawler $domCrawler;

    /**
     * @var SearchResult[]
     */
    private ?array $results = null;

    public function __construct(public string $html, private int $extractedAt = 0)
    {
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
     * @return SearchResult[]
     */
    public function getResults(bool $organicOnly = true): array
    {
        if (false === $organicOnly && null !== $this->results) {
            return $this->results;
        }

        $xpath = $this->isMobileSerp() ? self::RESULT_SELECTOR : self::RESULT_SELECTOR_DESKTOP;
        $nodes = $this->domCrawler->filterXpath($xpath);
        $toReturn = [];

        $i = 0;
        if ($this->containsSerpFeature('PositionZero')) {
            $toReturn[0] = $this->getPositionsZero();
            ++$i;
        }

        foreach ($nodes as $k => $node) {
            // skip if you are in ads
            $ads = null !== $nodes->eq($k)->closest('#tads, #bottomads') ? true : false;
            if ($organicOnly && $ads) {
                continue;
            }

            $result = $this->extractResultFrom($node, $ads);
            if (null === $result) {
                continue;
            }

            $toReturn[$i] = $result;
            $toReturn[$i]->pos = $i + 1;
            ++$i;
        }

        if (false === $organicOnly) {
            $this->results = $toReturn;
        }

        return $toReturn;
    }

    private function extractResultFrom(\DOMNode $node, bool $ads = false): ?SearchResult
    {
        $domCrawler = new Crawler($node);
        $linkNode = $domCrawler->filter('a')->getNode(0);
        if (null === $linkNode || ! $linkNode instanceof \DOMElement) {
            throw new \Exception('Google changes his selector. Please upgrade SERPExtractor (mobile  '.(int) $this->isMobileSerp().')');
        }

        // skip shopping Results
        if (str_starts_with($linkNode->getAttribute('href'), '/aclk?')) {
            return null;
        }

        $toReturn = new SearchResult();
        $toReturn->pixelPos = $this->getPixelPosFor($linkNode->getNodePath() ?? '');
        $toReturn->url = $linkNode->getAttribute('href');
        $toReturn->title = (new Crawler($linkNode))->text('');
        $toReturn->ads = $ads;

        return $toReturn;
    }

    protected function getPixelPosFor(string|\DOMNode $element): int
    {
        return 0;
    }

    public function containsSerpFeature(string $serpFeatureName, int &$pos = 0): bool
    {
        $xpaths = self::SERP_FEATURE_SELECTORS[$serpFeatureName];
        if (! $this->exists($xpaths)) {
            return false;
        }

        $pos = $this->getPixelPosFor($this->getNode($xpaths));

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
            ->filterXPath("//h2[text()='Extrait optimisé sur le Web']/ancestor::block-component//a[@class]")
            ->getNode(0);

        if (null === $linkNodePositionZero || ! $linkNodePositionZero instanceof \DOMElement) {
            file_put_contents('/tmp/debug.html', $this->html);

            throw new \LogicException('Google has changed its selector (position Zero)');
        }

        $toReturn = new SearchResult();
        $toReturn->pos = 1;
        $toReturn->pixelPos = $this->getPixelPosFor($linkNodePositionZero->getNodePath() ?? '');
        $toReturn->url = $linkNodePositionZero->getAttribute('href');
        $toReturn->title = $linkNodePositionZero->textContent;

        return $toReturn;
    }

    /**
     * @return string[]
     */
    public function getRelatedSearches(): array
    {
        $kw = [];
        $xpaths = $this->isMobileSerp() ? self::RELATED : self::RELATED_DESKTOP;
        foreach ($xpaths as $xpath) {
            $nodes = $this->domCrawler->filterXPath($xpath);
            foreach ($nodes as $node) {
                if ('' !== $node->textContent) {
                    $kw[] = $node->textContent;
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
        } catch (LogicException) {
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
            if (null !== $node && '' !== $node->nodeValue) {
                return $node;
            }
        }

        throw new \LogicException('`'.implode('`, ', $xpaths).'` not found');
    }

    public function __toJson(): string
    {
        return \Safe\json_encode([
            'version' => '1',
            'extractedAt' => $this->extractedAt,
            'resultStat' => $this->getNbrResults(),
            'serpFeatures' => $this->getSerpFeatures(),
            'relatedSearches' => $this->getRelatedSearches(),
            'results' => $this->getResults(),
        ]);
    }
}
