<?php

namespace PiedWeb\Google\Extractor;

use DOMElement;
use DOMNode;
use Exception;
use LogicException;
use PiedWeb\Extractor\Helper;
use PiedWeb\Google\Result\OrganicResult;
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

    public function __construct(public string $html)
    {
        $this->domCrawler = new Crawler($html);
    }

    private function isMobileSerp(): bool
    {
        return $this->exists([self::RESULT_SELECTOR]);
    }

    public function getNbrResults(): int
    {
        $node = null;
        if (! $this->exists(['//*[@id="resultStats"]|', '//*[@id="result-stats"]'], $node)) {
            return -1;
        }

        return (int) Helper::preg_replace_str('/[^0-9]/', '', $node->nodeValue ?? '');
    }

    /**
     * @return OrganicResult[]
     */
    public function getOrganicResults(): array
    {
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
            if (null !== $nodes->eq($k)->closest('#tads, #bottomads')) {
                continue;
            }

            $result = $this->extractResultFrom($node);
            if (null === $result) {
                continue;
            }

            $toReturn[$i] = $result;
            $toReturn[$i]->pos = $i + 1;
            ++$i;
        }

        return $toReturn;
    }

    private function extractResultFrom(DOMNode $node): ?OrganicResult
    {
        $domCrawler = new Crawler($node);
        $linkNode = $domCrawler->filter('a')->getNode(0);
        if (null === $linkNode || ! $linkNode instanceof DOMElement) {
            throw new Exception('Google changes his selector. Please upgrade SERPExtractor (mobile  '.(int) $this->isMobileSerp().')');
        }

        // skip shopping Results
        if (str_starts_with($linkNode->getAttribute('href'), '/aclk?')) {
            return null;
        }

        $toReturn = new OrganicResult();
        $toReturn->pixelPos = $this->getPixelPosFor($linkNode->getNodePath() ?? '');
        $toReturn->url = $linkNode->getAttribute('href');
        $toReturn->title = (new Crawler($linkNode))->text('');

        return $toReturn;
    }

    protected function getPixelPosFor(string|DOMNode $element): int
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

    public function getPositionsZero(): OrganicResult
    {
        $blockPositionZero = $this->domCrawler
            ->filterXPath("//h2[text()='Extrait optimisé sur le Web']")
            ->closest('block-component');
        if (null === $blockPositionZero
            || ! ($linkNodePositionZero = $blockPositionZero->filter('a')->getNode(0)) instanceof DOMElement) {
            throw new LogicException('Google has changed its selector (position Zero)');
        }

        $toReturn = new OrganicResult();
        $toReturn->pos = 1;
        $toReturn->pixelPos = $this->getPixelPosFor($linkNodePositionZero->getNodePath() ?? '');
        $toReturn->url = $linkNodePositionZero->getAttribute('href');
        $toReturn->title = $linkNodePositionZero->textContent;
        // $toReturn->node =$blockPositionZero;

        return $toReturn;
    }

    /**
     * @param string[] $xpaths
     */
    public function exists(array $xpaths, ?DOMNode &$node = null): bool
    {
        try {
            $node = $this->getNode($xpaths);

            return true;
        } catch (LogicException $logicException) {
            return false;
        }
    }

    /**
     * @param string[] $xpaths
     */
    public function getNode(array $xpaths): DOMNode
    {
        foreach ($xpaths as $xpath) {
            $node = $this->domCrawler->filterXPath($xpath)->getNode(0);
            if (null !== $node && '' !== $node->nodeValue) {
                return $node;
            }
        }

        throw new LogicException('`'.implode('`, ', $xpaths).'` not found');
    }
}
