<?php

namespace PiedWeb\Crawler;

use League\Csv\Reader;

use function Symfony\Component\String\u;

final class RecordPlayer
{
    /**
     * @var array<int, string>
     */
    private array $index = [];

    public function __construct(
        private readonly CrawlerConfig $config
    ) {
    }

    private function loadIndexFromPreviousCrawl(): void
    {
        if ([] !== $this->index) {
            return;
        }

        $indexFilePath = $this->config->getDataFolder().'/index.csv';
        if (! file_exists($indexFilePath)) {
            throw new \Exception("Previous crawl's data not found (index.csv)");
        }

        $csv = Reader::from($indexFilePath);
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! isset($r['id']) || ! isset($r['uri']) || ! \is_string($r['uri'])) {
                throw new \LogicException();
            }

            \assert(\is_scalar($r['id']));
            $this->index[(int) $r['id']] = $r['uri'];
        }
    }

    public function getUrlFromId(int $id): string
    {
        $this->loadIndexFromPreviousCrawl();

        if (! isset($this->index[$id])) {
            throw new \LogicException();
        }

        return $this->index[$id];
    }

    /**
     * @return array{'urls': array<string, Url>, 'counter': int, 'currentClick': int}
     */
    public function getDataFromPreviousCrawl(): array
    {
        $r = [];
        $dataFilePath = $this->config->getDataFolder().'/data.csv';
        if (! file_exists($dataFilePath)) {
            throw new \Exception("Previous crawl's data not found (index.csv)");
        }

        $urls = [];
        $counter = 0;

        $csv = Reader::from($dataFilePath);
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! isset($r['uri'])) {
                throw new \LogicException();
            }

            \assert(\is_string($r['uri']));
            $urls[$r['uri']] = new Url($this->config->getBase().$r['uri']);
            if (($r['can_be_crawled'] ?? '') === ''
                 // we will retry network errror
                && NetworkStatus::NETWORK_ERROR != ($r['network_status'] ?? true)
            ) {
                foreach ($r as $k => $v) {
                    $kFunction = 'set'.u($k)->camel()->toString()
                        .(isset(Url::ARRAY_EXPORTED[$k]) ? 'FromString' : '');
                    if (! method_exists($urls[$r['uri']], $kFunction)) {
                        continue;
                    }

                    $urls[$r['uri']]->$kFunction($v);
                }

                ++$counter;
            }
        }

        $currentClick = $r['click'] ?? 0;
        \assert(\is_scalar($currentClick));
        $currentClick = (int) $currentClick;

        return [
            'urls' => $urls,
            'counter' => $counter,
            'currentClick' => $currentClick,
        ];
    }
}
