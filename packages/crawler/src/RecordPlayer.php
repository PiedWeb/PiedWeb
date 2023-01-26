<?php

namespace PiedWeb\Crawler;

use League\Csv\Reader;
use Stringy\Stringy;

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

        $csv = Reader::createFromPath($indexFilePath, 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! \is_array($r) || ! isset($r['id']) || ! isset($r['uri']) || ! \is_string($r['uri'])) {
                throw new \LogicException();
            }

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
     * @return array{'urls': Url[], 'counter': int, 'currentClick': int}
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

        $csv = Reader::createFromPath($dataFilePath, 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! \is_array($r) || ! isset($r['uri'])) {
                throw new \LogicException();
            }

            $urls[$r['uri']] = Url::initialize($this->config->getBase().$r['uri']);
            if (! empty($r['can_be_crawled'] ?? '')
                 // we will retry network errror
                && NetworkStatus::NETWORK_ERROR != ($r['network_status'] ?? true)
            ) {
                foreach ($r as $k => $v) {
                    $kFunction = 'set'.Stringy::create($k)->camelize()
                        .(isset(Url::ARRAY_EXPORTED[$k]) ? 'FromString' : '');
                    if (! method_exists($urls[$r['uri']], $kFunction)) {
                        continue;
                    }

                    $urls[$r['uri']]->$kFunction($v);
                }

                ++$counter;
            }
        }

        $currentClick = (int) ($r['click'] ?? 0);

        return [
            'urls' => $urls,
            'counter' => $counter,
            'currentClick' => $currentClick,
        ];
    }
}
