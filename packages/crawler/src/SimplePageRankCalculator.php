<?php

namespace PiedWeb\Crawler;

use League\Csv\Reader;

/**
 * Page Rank Calculator.
 */
final class SimplePageRankCalculator extends AbstractPageRankCalculator
{
    private readonly \PiedWeb\Crawler\CrawlerConfig $config;

    public function __construct(string $id, string $dataDirectory = null)
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
            if (! \is_array($r) || ! isset($r['To']) || ! isset($r['From'])) {
                throw new \LogicException();
            }

            $r['To'] = (int) $r['To'];

            if (! isset($this->linksTo[$r['To']])) {
                $this->linksTo[$r['To']] = [];
            }

            $r['From'] = (int) $r['From'];
            $this->linksTo[$r['To']][] = $r['From'];

            $this->nbrLinksFrom[$r['From']] = ($this->nbrLinksFrom[$r['From']] ?? 0) + 1;
        }
    }
}
