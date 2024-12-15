<?php

namespace PiedWeb\Google\Result;

final class BusinessResult
{
    public function __construct(
        public string $cid = '',
        public string $mid = '',
        public string $name = '',
        public int $organicPos = 0,
        public int $position = 0,
        public int $pixelPos = 0,
        public bool $ads = false,
    ) {
    }
}
