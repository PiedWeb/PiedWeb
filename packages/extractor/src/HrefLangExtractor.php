<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class HrefLangExtractor
{
    public function __construct(
        private readonly Crawler $crawler,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function getHrefLangList(): array
    {
        $toReturn = [];
        $links = $this->crawler->filterXPath('//link[@hreflang]')->extract(['hreflang', 'href']);
        foreach ($links as $link) {
            \assert(\is_array($link));
            \assert(isset($link[0]));
            \assert(\is_string($link[0]) || \is_int($link[0]));
            if (isset($toReturn[$link[0]])) {
                continue;
            }

            \assert(\is_scalar($link[1]));
            $toReturn[(string) $link[0]] = (string) $link[1];
        }

        return $toReturn;
    }
}
