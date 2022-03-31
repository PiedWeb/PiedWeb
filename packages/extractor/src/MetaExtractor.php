<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class MetaExtractor
{
    public function __construct(
        private Crawler $crawler
    ) {
    }

    public function get(string $name): ?string
    {
        $meta = $this->crawler->filter('meta[name='.$name.']');

        return $meta->count() > 0
            ? (null !== $meta->attr('content') ? Helper::clean($meta->attr('content')) : '')
            : null;
    }
}
