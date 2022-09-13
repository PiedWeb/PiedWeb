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
    private Crawler $domCrawler;

    public function __construct(public string $html)
    {
        $this->domCrawler = new Crawler($html);
    }

    private function isMobileSerp(): bool
    {
        return null !== $this->domCrawler->filter('a[role=presentation] [role="heading"]')->getNode(0);
    }

    public function getNbrResults(): int
    {
        $node = $this->domCrawler->filter('#resultStats')->getNode(0)
            ?? $this->domCrawler->filter('#result-stats')->getNode(0)
                ?? null;

        if (null === $node) {
            return 0;
        }

        return (int) Helper::preg_replace_str('/[^0-9]/', '', $node->nodeValue ?? '');
    }

    /**
     * @return OrganicResult[]
     */
    public function getOrganicResults(): array
    {
        $nodesSelector = $this->isMobileSerp() ? 'a[role=presentation]' : 'h3';
        $nodes = $this->domCrawler->filter($nodesSelector);
        $toReturn = [];
        $i = 0;
        if ($this->containsPositionZero()) {
            $toReturn[0] = $this->getPositionsZero();
            ++$i;
        }

        foreach ($nodes as $k => $node) {
            // skip if you are in ads
            if (null !== $nodes->eq($k)->closest('#tads, #bottomads')) {
                continue;
            }

            $node = $this->isMobileSerp() ? $node : $node->parentNode;
            if (null === $node || ! $node instanceof DOMElement) {
                throw new Exception('Google changes his selector. Please upgrade SERPExtractor (mobile  '.(int) $this->isMobileSerp().')');
            }

            $toReturn[$i] = new OrganicResult();
            $toReturn[$i]->pos = $i + 1;
            $toReturn[$i]->pixelPos = $this->getPixelPosFor($node->getNodePath() ?? '');
            $toReturn[$i]->url = $node->getAttribute('href');
            $toReturn[$i]->title = $this->getTitlteFromTitleLinkNode($node);
            $toReturn[$i]->description = $this->getDescriptionFromTitleLinkNode($node);
            ++$i;
        }

        return $toReturn;
    }

    protected function getPixelPosFor(string $elementXpath): int
    {
        return 0;
    }

    private function getTitlteFromTitleLinkNode(DOMElement $node): string
    {
        $crawler = (new Crawler($node));

        if ($this->isMobileSerp()) {
            $node = $crawler->filter('div')->getNode(0);
        } else {
            $node = $crawler->filter('h3')->getNode(0);
        }

        return $node instanceof \DOMNode ? $node->textContent : '';
    }

    private function getDescriptionFromTitleLinkNode(DOMElement $node): string
    {
        $wrapper = $this->getParentNode($node, 5);
        if (null === $wrapper) {
            return '';
        }

        $crawler = new Crawler($wrapper);
        $description = $crawler->filter('div[data-content-feature]')->getNode(0);

        return null === $description ? '' : strip_tags($description->textContent);
    }

    private function getParentNode(DOMNode $node, int $level, int $currentLevel = 0): ?DOMNode
    {
        $parentNode = $node->parentNode;
        ++$currentLevel;
        if ($currentLevel == $level) {
            return $parentNode;
        }

        if (null === $parentNode) {
            return null;
        }

        return $this->getParentNode($parentNode, $level, $currentLevel);
    }

    public function containsAds(): bool
    {
        return $this->exist(['.//*[@id="tads"]|.//*[@id="bottomads"]']);
    }

    public function containsImageBlock(): bool
    {
        return $this->exist(["//span[text()='Images']", "//h3[starts-with(text(), 'Images correspondant')]"]);
    }

    public function containsMapsBlock(): bool
    {
        return $this->exist(["//div[text()='Adresses']"]);
    }

    public function containsPositionZero(): bool
    {
        return $this->exist(["//h2[text()='Extrait optimisé sur le Web']"]);
    }

    public function containsKnowledgePanel(): bool
    {
        return $this->exist(['//div[contains(concat(" ",normalize-space(@class)," ")," kp-wholepage ")]']);
    }

    public function containsNews(): bool
    {
        return $this->exist(['//span[text()="À la une"]']);
    }

    public function containsPeopleAlsoAsked(): bool
    {
        return $this->exist(['//span[text()="Autres questions posées"]']);
    }

    public function containsVideo(): bool
    {
        return $this->exist([
            '//span[text()="Vidéos"]',
            '//div[contains( @aria-label,"second")]',
        ]);
    }

    public function containsReviews(): bool
    {
        return $this->exist([
            '//span[contains( @aria-label,"Note")]',
        ]);
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
    public function exist(array $xpaths): bool
    {
        try {
            $this->getNode($xpaths);

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
