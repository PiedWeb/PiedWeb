<?php

namespace PiedWeb\Extractor;

use PiedWeb\TextAnalyzer\CleanText;
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

        return $found->count() > 0 ? CleanText::fixEncoding($found->text()) : null;
    }

    public function getFirst(string $selector): ?string
    {
        $found = $this->crawler->filter($selector);

        if (0 === $found->count()) {
            return null;
        }

        return CleanText::fixEncoding($found->eq(0)->text());
    }

    public function getCount(string $selector): int
    {
        $found = $this->crawler->filter($selector);

        return $found->count();
    }

    public function getUnique(string $selector = 'title'): ?string
    {
        $found = $this->crawler->filter($selector);

        if (0 === $found->count()) {
            return null;
        }

        if ($found->count() > 1) {
            return '⚠ '.$found->count().' `'.$selector.'` - '.CleanText::fixEncoding($found->text());
        }

        return CleanText::fixEncoding($found->eq(0)->text());
    }
}
