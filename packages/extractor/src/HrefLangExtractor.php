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
            if ('x-default' === $link[0]) {
                continue;
            }

            if (isset($toReturn[$link[0]])) {
                continue;
            }

            $toReturn[(string) $link[0]] = (string) $link[1];
        }

        return $toReturn;
    }
}
