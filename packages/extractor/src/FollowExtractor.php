<?php

namespace PiedWeb\Extractor;

use Spatie\Robots\RobotsHeaders;
use Symfony\Component\DomCrawler\Crawler;

final class FollowExtractor
{
    public function __construct(
        private Crawler $crawler,
        private string $headers
    ) {
    }

    private function metaNofollow(): bool
    {
        $meta = (new MetaExtractor($this->crawler))->get('googlebot') ?? '';
        $generic = (new MetaExtractor($this->crawler))->get('robots') ?? '';

        return str_contains($meta, 'nofollow') || str_contains($generic, 'nofollow');
    }

    public function mayFollow(): bool
    {
        $robotsHeaders = new RobotsHeaders(explode(\PHP_EOL, $this->headers));

        return $robotsHeaders->mayFollow() && ! $this->metaNofollow() ? true : false;
    }
}
