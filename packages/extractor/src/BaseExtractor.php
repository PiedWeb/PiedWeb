<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class BaseExtractor
{
    public function __construct(
        private Crawler $crawler,
    ) {
    }

    public function get(): ?Url
    {
        $base = $this->crawler->filter('base')->attr('href');
        if ($base && filter_var($base, \FILTER_VALIDATE_URL)) {
            return new Url($base);
        }

        return null;
    }
}
