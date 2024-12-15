<?php

namespace PiedWeb\Google\Result;

final class SearchResult
{
    public function __construct(
        public int $organicPos,
        public int $position,
        public string $url,
        public string $title,
        public string $description = '',
        public int $pixelPos = 0,
        public bool $ads = false,
    ) {
    }
}
