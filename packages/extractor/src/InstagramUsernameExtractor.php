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
        if (preg_match('#(https?://(www.)?instagram.com/([^/]+))/?(\?hl=([a-z]+))?"#Ui', $this->html, $match)) {
            return $match[3];
        }

        return '';
    }

    public function extractYoutubeChannel(): string
    {
        if (preg_match('#https?:\/\/(www\.)?youtube\.com\/(@[a-z0-9_-]+|channel\/[a-z0-9_-]+|c\/[a-z0-9_-]+|user\/[a-z0-9_-]+)#i', $this->html, $match)) {
            return $match[0];
        }

        return '';
    }
}
