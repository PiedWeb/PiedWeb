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
        private bool $gotoWrapped = false,
        private bool $gotoResolvedInline = false,
    ) {
    }

    public function wasGotoWrapped(): bool
    {
        return $this->gotoWrapped;
    }

    public function wasGotoResolvedInline(): bool
    {
        return $this->gotoResolvedInline;
    }
}
