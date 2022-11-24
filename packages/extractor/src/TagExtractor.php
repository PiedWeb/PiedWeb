<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class TagExtractor
{
    public function __construct(
        private readonly Crawler $crawler
    ) {
    }

    public function get(string $selector): ?string
    {
        $found = $this->crawler->filter($selector);

        return $found->count() > 0 ? Helper::clean($found->text()) : null;
    }

    public function getUnique(string $selector = 'title'): ?string
    {
        $found = $this->crawler->filter($selector);

        if (0 === $found->count()) {
            return null;
        }

        if ($found->count() > 1) {
            return '⚠ '.$found->count().' `'.$selector.'` - '.Helper::clean($found->text());
        }

        return Helper::clean($found->eq(0)->text());
    }
}
