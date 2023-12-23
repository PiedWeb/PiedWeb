<?php

namespace PiedWeb\Extractor;

use PiedWeb\TextAnalyzer\CleanText;
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
            ? (null !== ($t = $meta->attr('content')) ? CleanText::fixEncoding($t) : '')
            : null;
    }
}
