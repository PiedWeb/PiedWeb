<?php

namespace PiedWeb\Extractor;

class BreadcrumbItem
{
    public function __construct(private string $url, private string $name)
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCleanName(): string
    {
        return substr(strip_tags($this->name), 0, 100);
    }
}
