<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class BaseExtractor
{
    public function __construct(
        private readonly Crawler $crawler,
    ) {
    }

    public function get(): ?Url
    {
        if (($base = $this->crawler->filter('base')->getNode(0)) === null) {
            return null;
        }

        $baseHref = (new Crawler($base))->attr('href');
        if (! $baseHref) {
            return null;
        }

        if (! filter_var($baseHref, \FILTER_VALIDATE_URL)) {
            return null;
        }

        return new Url($baseHref);
    }
}
