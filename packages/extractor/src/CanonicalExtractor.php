<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class CanonicalExtractor
{
    private ?string $canonical = null;

    public function __construct(
        private readonly Url $urlRequested,
        private readonly Crawler $crawler
    ) {
        $this->init();
    }

    private function init(): ?string
    {
        $canonical = $this->crawler->filter('link[rel=canonical]');

        return $this->canonical = $canonical->count() > 0 ? ($canonical->attr('href') ?? '') : null;
    }

    public function get(): ?string
    {
        return $this->canonical;
    }

    public function canonicalExists(): bool
    {
        return null != $this->canonical;
    }

    public function isCanonicalPartiallyCorrect(): bool
    {
        return $this->urlRequested->getAbsoluteUri() == $this->canonical;
    }

    public function ifCanonicalExistsIsItCorrectOrPartiallyCorrect(): bool
    {
        if (! $this->canonicalExists()) {
            return true;
        }

        if ($this->isCanonicalCorrect()) {
            return true;
        }

        return $this->isCanonicalPartiallyCorrect();
    }

    public function isCanonicalCorrect(): bool
    {
        $canonical = $this->canonical;

        if (null === $canonical) {
            throw new \Exception('You must check if canonical exists before');
        }

        if ($this->urlRequested->__toString() === $canonical) {
            return true;
        }

        $pregMatch = preg_match('#^.+?[^\/:](?=[?\/]|$)#', $this->urlRequested->__toString(), $match);

        // check for http://example.tld or http://example.tld/
        return false !== $pregMatch
                && $match[0] === ltrim($this->urlRequested->__toString(), '/')
                && ($match[0] === $canonical || $match[0].'/' === $canonical);
    }
}
