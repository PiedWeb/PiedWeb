<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class MetaExtractor
{
    public function __construct(
        private readonly Crawler $crawler
    ) {
    }

    public function get(string $name): ?string
    {
        $meta = $this->crawler->filter('meta[name="'.$name.'"]');

        if (0 === $meta->count()) {
            $meta = $this->crawler->filter('meta[property="'.$name.'"]');
        }

        return $meta->count() > 0
            ? (null !== $meta->attr('content') ? Helper::clean($meta->attr('content')) : '')
            : null;
    }
}
