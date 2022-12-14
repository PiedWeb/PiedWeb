<?php

namespace PiedWeb\Extractor;

use Spatie\Robots\RobotsHeaders;
use Symfony\Component\DomCrawler\Crawler;

final class FollowExtractor
{
    public function __construct(
        private readonly Crawler $crawler,
        private readonly string $headers
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
        if (!$robotsHeaders->mayFollow()) {
            return false;
        }
        return ! $this->metaNofollow();
    }
}
