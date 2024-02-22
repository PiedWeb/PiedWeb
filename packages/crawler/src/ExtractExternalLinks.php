<?php

namespace PiedWeb\Crawler;

final class ExtractExternalLinks
{
    private readonly string $dir;

    /**
     * @var array<string, array<string>>
     */
    private array $external = [];

    private readonly CrawlerConfig $config;

    public function __construct(
        string $id,
        ?string $dataDirectory = null
    ) {
        $this->config = CrawlerConfig::loadFrom($id, $dataDirectory);
        $this->dir = $this->config->getDataFolder().'/links';
        $this->scanLinksDir();
    }

    private function scanLinksDir(): void
    {
        if ($resource = opendir($this->dir)) {
            while (false !== ($filename = readdir($resource))) {
                if (str_starts_with($filename, 'From_')) {
                    $this->harvestExternalLinks(
                        trim(\Safe\file_get_contents($this->dir.'/'.$filename)),
                        $this->config->getRecordPlayer()->getUrlFromId((int) substr($filename, \strlen('From_')))
                    );
                }
            }

            closedir($resource);
        }
    }

    private function harvestExternalLinks(string $strUrlsLinked, string $from): void
    {
        if ('' === $strUrlsLinked) {
            return;
        }

        $lines = explode(\chr(10), $strUrlsLinked);

        foreach ($lines as $line) {
            if (! str_starts_with($line, $this->config->getBase())) {
                if (! isset($this->external[$line])) {
                    $this->external[$line] = [];
                }

                $this->external[$line][] = $from;
            }
        }
    }

    /**
     * @return array<string, array<string>>
     */
    public function get(): array
    {
        return $this->external;
    }
}
