<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class CanonicalExtractor
{
    public function __construct(
        private Url $urlRequested,
        private Crawler $crawler
    ) {
    }

    public function get(): ?string
    {
        $canonical = $this->crawler->filter('link[rel=canonical]');

        return $canonical->count() > 0 ? (null !== $canonical->attr('href') ? $canonical->attr('href') : '') : null;
    }

    public function isCanonicalCorrect(): bool
    {
        $canonical = $this->get();

        if (null === $canonical) {
            return true;
        }

        if ($this->urlRequested->__toString() == $canonical) {
            return true;
        }

        $pregMatch = preg_match('/^.+?[^\/:](?=[?\/]|$)/', $this->urlRequested->__toString(), $match);
        // check for http://example.tld or http://example.tld/
        return false !== $pregMatch
                && $match[0] === ltrim($this->urlRequested->__toString(), '/')
                && ($match[0] == $canonical || $match[0].'/' == $canonical);
    }
}
