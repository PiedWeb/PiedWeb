<?php

namespace PiedWeb\Extractor;

final class InstagramUsernameExtractor
{
    public function __construct(
        private readonly string $html,
    ) {
    }

    public function extract(): string
    {
        if (preg_match('#(https?://(www.)?instagram.com/([^/]+))/?"#Ui', $this->html, $match)) {
            return $match[3];
        }

        return '';
    }
}
