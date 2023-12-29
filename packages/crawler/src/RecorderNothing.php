<?php

namespace PiedWeb\Crawler;

use PiedWeb\Extractor\Link;

final class RecorderNothing extends Recorder
{
    public function __construct()
    {
    }

    public function cache(mixed $response, Url $url): void
    {
    }

    public function getCacheFilePath(Url $url): string
    {
        return '';
    }

    /**
     * @param array<Url> $urls
     */
    public function record(array $urls): bool
    {
        return true;
    }

    /**
     * @param array<Url|null> $urls
     * @param Link[]          $links
     */
    public function recordLinksIndex(string $base, Url $from, array $urls, array $links): void
    {
    }
}
