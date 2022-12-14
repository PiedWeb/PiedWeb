<?php

namespace PiedWeb\Crawler;

use League\Csv\Reader;

/**
 * Page Rank Calculator.
 */
final class SimplePageRankCalculator
{
    private readonly \PiedWeb\Crawler\CrawlerConfig $config;

    private ?int $pagesNbr = null;

    /**
     * @var array<int, float>
     */
    private array $results;

    private int $maxIteration = 10000;

    /**
     * @var array<int, array<int>>
     */
    private array $linksTo = [];

    /**
     * @var array<int, int>
     */
    private array $nbrLinksFrom = [];

    private float $dampingFactor = 0.85;

    public function __construct(string $id, ?string $dataDirectory = null)
    {
        $this->config = CrawlerConfig::loadFrom($id, $dataDirectory);

        $this->initLinksIndex();
        $this->calcul();
    }

    public function record(): string
    {
        // merge it with previous data harvested
        $data = $this->config->getRecordPlayer()->getDataFromPreviousCrawl();
        $urls = $data['urls'];

        foreach ($urls as $k => $url) {
            if (isset($this->results[$url->getId()])) {
                $urls[$k]->setPagerank($this->results[$url->getId()]);
            }
        }

        (new Recorder($this->config->getDataFolder(), $this->config->cacheMethod))->record($urls);

        // return data filepath
        return realpath($this->config->getDataFolder()).'/data.csv';
    }

    private function calcul(): void
    {
        for ($iteration = 0; $iteration < $this->maxIteration; ++$iteration) {
            $ids = array_keys($this->linksTo);
            foreach ($ids as $id) {
                $sumPR = 0;
                foreach ($this->getLinksTo($id) as $link) {
                    $sumPR += $this->results[$link] ?? 0 / $this->getNbrLinksFrom($link);
                }

                $this->results[$id] = $this->dampingFactor * $sumPR + (1 - $this->dampingFactor) / $this->getPagesNbr();
            }
        }
    }

    private function getPagesNbr(): int
    {
        return $this->pagesNbr ??= \count($this->linksTo);
    }

    /**
     * @return int[]
     */
    private function getLinksTo(int $id): array
    {
        return $this->linksTo[$id];
    }

    private function getNbrLinksFrom(int $id): int
    {
        return $this->nbrLinksFrom[$id];
    }

    /**
     * @noRector
     */
    private function initLinksIndex(): void
    {
        $csv = Reader::createFromPath($this->config->getDataFolder().Recorder::LINKS_DIR.'/Index.csv', 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! \is_array($r) || ! isset($r['To']) || ! isset($r['From'])) {
                throw new \LogicException();
            }

            $r['To'] = (int) $r['To'];

            if (! isset($this->linksTo[$r['To']])) {
                $this->linksTo[$r['To']] = [];
            }

            $this->linksTo[$r['To']][] = $r['From'] = (int) $r['From'];

            $this->nbrLinksFrom[$r['From']] = ($this->nbrLinksFrom[$r['From']] ?? 0) + 1;
        }
    }
}
