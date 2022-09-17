<?php

namespace PiedWeb\Google\Result;

final class SearchResult
{
    public int $pos;

    public int $pixelPos = 0;

    public string $url;

    public string $title;

    public string $description = '';

    public bool $ads = false;
}
