<?php

namespace PiedWeb\Google\Extractor;

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

        return (int) (preg_replace('/[^0-9]/', '', $node->nodeValue));
    }

    /**
     * @return OrganicResult[]
     */
    public function getOrganicResults(): array
    {
        $nodes = $this->isMobileSerp() ?
            $this->domCrawler->filter('a[oncontextmenu][role=presentation]')
            : $this->domCrawler->filter('.g[data-hveid] a');
        $toReturn = [];
        foreach ($nodes as $k => $node) {
            $toReturn[$k] = new OrganicResult();
            $toReturn[$k]->pos = $k + 1;
            $toReturn[$k]->pixelPos = 0;
            $toReturn[$k]->url = $node->getAttribute('href'); // @phpstan-ignore-line
            $toReturn[$k]->title = $node->nodeValue;
        }

        return $toReturn;
    }
}
