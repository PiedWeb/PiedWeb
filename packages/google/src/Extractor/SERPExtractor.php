<?php

namespace PiedWeb\Google\Extractor;

use DOMElement;
use Exception;
use PiedWeb\Extractor\Helper;
use PiedWeb\Google\Result\OrganicResult;
use Symfony\Component\DomCrawler\Crawler;

class SERPExtractor
{
    private Crawler $domCrawler;

    public function __construct(public string $html)
    {
        file_put_contents('debug.html', $html);
        $this->domCrawler = new Crawler($html);
    }

    private function isMobileSerp(): bool
    {
        return null !== $this->domCrawler->filter('a[oncontextmenu] [role="heading"]')->getNode(0);
    }

    public function getNbrResults(): int
    {
        $node = $this->domCrawler->filter('#resultStats')->getNode(0)
            ?? $this->domCrawler->filter('#result-stats')->getNode(0)
                ?? null;

        if (null === $node) {
            return 0;
        }

        return (int) (Helper::preg_replace_str('/[^0-9]/', '', $node->nodeValue));
    }

    /**
     * @return OrganicResult[]
     */
    public function getOrganicResults(): array
    {
        $nodes = $this->isMobileSerp() ?
            $this->domCrawler->filter('a[oncontextmenu][role=presentation]')
            : $this->domCrawler->filter('h3');
        $toReturn = [];
        $i = 0;
        foreach ($nodes as $k => $node) {
            $node = $this->isMobileSerp() ? $node : $node->parentNode;
            if (null === $node || ! $node instanceof DOMElement) {
                throw new Exception('Google changes his selector. Please upgrade SERPExtractor (mobile  '.(int) $this->isMobileSerp().')');
            }

            $toReturn[$k] = new OrganicResult();
            $toReturn[$k]->pos = $i + 1;
            ++$i;
            $toReturn[$k]->pixelPos = 0;
            $toReturn[$k]->url = $node->getAttribute('href');
            $toReturn[$k]->title = $this->getTitlteFromTitleLinkNode($node);
            $toReturn[$k]->description = $this->getDescriptionFromTitleLinkNode($node);
        }

        return $toReturn;
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

    private function getParentNode(DOMElement $node, int $level, int $currentLevel = 0): ?DOMElement
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
}
