<?php

namespace PiedWeb\Crawler;

use League\Csv\Reader;

/**
 * Not used anymore...
 */
class LinksVisualizer
{
    protected \PiedWeb\Crawler\CrawlerConfig $config;

    /**
     * @var array{'nodes': array<mixed>, 'links': array<mixed>}
     */
    protected array $results = ['nodes' => [], 'links' => []];

    public function __construct(string $id, ?string $dataDirectory = null)
    {
        $this->config = CrawlerConfig::loadFrom($id, $dataDirectory);

        // $this->loadNodes();
        // $this->loadLinks();

        file_put_contents(
            $this->config->getDataFolder().'/pagerank.html',
            file_get_contents(__DIR__.'/Resources/PageRankVisualizer.html')
        );
        /*
        file_put_contents(
            $this->config->getDataFolder().Recorder::LINKS_DIR.'/data.json',
            json_encode($this->results, JSON_PRETTY_PRINT)
        );**/
    }

    protected function loadLinks(): void
    {
        $csv = Reader::createFromPath($this->config->getDataFolder().Recorder::LINKS_DIR.'/Index.csv', 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();
        foreach ($records as $r) {
            if (! \is_array($r) || ! isset($r['To'])) {
                throw new \LogicException();
            }

            if ($r['To'] > 0 // pas de liens externe
                && isset($this->results['nodes'][$r['From']]) && isset($this->results['nodes'][$r['To']])
            ) {
                $this->results['links'][] = ['target' => $r['From'], 'source' => $r['To']];
            }
        }

        $this->results['nodes'] = array_values($this->results['nodes']);
    }

    protected function loadNodes(): void
    {
        $urls = $this->config->getRecordPlayer()->getDataFromPreviousCrawl()['urls'];

        foreach ($urls as $url) {
            if (1 == $url->getMimeType()) { // seulement html
                $this->results['nodes'][$url->getId()] = [
                    'id' => $url->getId(),
                    'pagerank' => $url->getPagerank(),
                    'uri' => $url->getUri(),
                ];
            }
        }
    }
}
