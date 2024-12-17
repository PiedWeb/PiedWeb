<?php

namespace PiedWeb\Crawler;

use League\Csv\Reader;

/**
 * Page Rank Calculator.
 */
final class SimplePageRankCalculator extends AbstractPageRankCalculator
{
    private readonly CrawlerConfig $config;

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

    private function initLinksIndex(): void
    {
        $csv = Reader::createFromPath($this->config->getDataFolder().Recorder::LINKS_DIR.'/Index.csv', 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! isset($r['To']) || ! isset($r['From'])) {
                throw new \LogicException();
            }

            \assert(\is_scalar($r['To']));
            $to = (int) $r['To'];

            if (! isset($this->linksTo[$to])) {
                $this->linksTo[$to] = [];
            }

            \assert(\is_scalar($r['From']));
            $from = (int) $r['From'];
            $this->linksTo[$to][] = $from;

            $this->nbrLinksFrom[$from] = ($this->nbrLinksFrom[$from] ?? 0) + 1;
        }
    }
}
