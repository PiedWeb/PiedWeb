<?php

namespace PiedWeb\Crawler;

use PiedWeb\Extractor\Link;
use PiedWeb\Extractor\Url as ExtractorUrl;

/**
 * @see \PiedWeb\Crawler\Test\CrawlerTest
 */
final class Crawler
{
    /** @var class-string<\PiedWeb\Crawler\CrawlerUrl> */
    private string $harvester = \PiedWeb\Crawler\CrawlerUrl::class;

    private int $counter = 0;

    /**
     * @var array<string, Url|null>
     */
    private array $urls = [];

    /** @var string[] */
    private array $everCrawled = [];

    private bool $nothingUpdated = true;

    public readonly CrawlerConfig $config;

    public function __construct(
        CrawlerConfig|string $config,
        public readonly bool $debug = false,
        private int $currentClick = 0, // = depth
    ) {
        $this->config = \is_string($config) ? (new CrawlerConfig())->setStartUrl($config) : $config;
    }

    public static function continue(
        string $id,
        bool $debug = true,
        string $dataDirectory = null
    ): self {
        $config = CrawlerConfig::loadFrom($id, $dataDirectory);
        $current = new self($config, $debug);

        $dataFromPreviousCrawl = $current->config->getRecordPlayer()->getDataFromPreviousCrawl();
        $current->counter = $dataFromPreviousCrawl['counter'];
        $current->currentClick = $dataFromPreviousCrawl['currentClick'];
        $current->urls = $dataFromPreviousCrawl['urls'];

        return $current;
    }

    public static function restart(
        string $id,
        bool $fromCache = false,
        bool $debug = true,
        string $dataDirectory = null
    ): self {
        $config = CrawlerConfig::loadFrom($id, $dataDirectory);
        $current = new self($config, $debug);
        if ($fromCache) {
            $current->harvester = \PiedWeb\Crawler\CrawlerUrlFromCache::class;
        }

        exec('rm -rf '.$current->config->getDataFolder().Recorder::LINKS_DIR); // reset Links
        $current->urls[$current->config->getStartUrl()->getAbsoluteUri()] = null;

        return $current;
    }

    public function crawl(): bool
    {
        $this->urls[$this->config->getStartUrl()->getAbsoluteUri()] = null;

        $this->debugInitCrawlLoop();

        $absoluteUriList = array_keys($this->urls);
        foreach ($absoluteUriList as $i => $absoluteUri) {
            if (\in_array($absoluteUri, $this->everCrawled, true)) {
                continue;
            }

            if (0 !== $i) {
                /** @psalm-suppress ArgumentTypeCoercion */
                usleep($this->config->sleepBetweenReqInMs);
            }

            $this->everCrawled[] = $absoluteUri;
            $this->crawlUrl($absoluteUri);
        }

        ++$this->currentClick;

        // Record after each Level:
        $this->config->getRecorder()->record($this->getUrls());

        $record = $this->nothingUpdated || $this->currentClick >= $this->config->depthLimit;

        return $record ? true : $this->crawl();
    }

    private function getUrl(string $absoluteUri): Url
    {
        return $this->urls[$absoluteUri] ?? $this->urls[$absoluteUri] = new Url($this->config->getBase().$absoluteUri, $this->currentClick);
    }

    public function firstUrl(): Url
    {
        if (($firstUrl = $this->urls[key($this->urls)] ?? null) === null) {
            throw new \Exception();
        }

        return $firstUrl;
    }

    /**
     * @return Url[]
     */
    public function getUrls(): array
    {
        return array_filter($this->urls, static fn ($url): bool => null !== $url);
    }

    private function crawlUrl(string $absoluteUri): void
    {
        if (null !== $this->urls[$absoluteUri] && null !== $this->urls[$absoluteUri]->getCanBeCrawled()) {
            $this->debug('déjà crawlée');

            return;
        }

        $this->debugCrawlUrl($absoluteUri);
        $this->nothingUpdated = false;
        ++$this->counter;

        $url = $this->getUrl($absoluteUri);
        if (! $this->canBeCrawled($url)) {
            $this->debug('can`t be crawled');

            return;
        }

        /** @psalm-suppress UnsafeInstantiation */
        new $this->harvester($url, $this->config); // CrawlerUrl

        $this->updateInboundLinksAndUrlsToParse($url, $url->getLinks());
        $url->setDiscovered(\count($this->urls));

        $this->config->getRecorder()->recordLinksIndex($this->config->getBase(), $url, $this->urls, $url->getLinks());

        $this->autosave();
    }

    private function autosave(): void
    {
        if (0 !== $this->counter && $this->counter / $this->config->autosave == round($this->counter / $this->config->autosave)) {
            $this->debug('    --- auto-save');
            $this->config->getRecorder()->record($this->getUrls());
        }
    }

    private function canBeCrawled(Url $url): bool
    {
        return $url->getCanBeCrawled() ??
            $url->setCanBeCrawled($this->config->getVirtualRobots()
                ->allows($this->config->getBase().$url->getUri(), $this->config->userAgent));
    }

    /**
     * @param Link[] $links
     */
    public function updateInboundLinksAndUrlsToParse(Url $url, array $links): void
    {
        $everAdd = [];
        foreach ($links as $link) {
            if (Link::LINK_INTERNAL !== $link->getType()) {
                continue;
            }

            $newUrl = (new ExtractorUrl($link->getUrl()));
            $newUri = $newUrl->getAbsoluteUri();
            $this->urls[$newUri] ??= new Url(
                $newUrl->getDocumentUrl()->__toString(),
                $this->currentClick + 1
            );
            if (isset($everAdd[$newUri])) {
                continue;
            }

            $everAdd[$newUri] = 1;
            if (! $link->mayFollow()) {
                $this->urls[$newUri]->incrementInboundLinksNofollow();
            } else {
                $this->urls[$newUri]->incrementInboundLinks();
            }
        }
    }

    private function debug(string $text): void
    {
        if ($this->debug) {
            echo $text.\PHP_EOL;
        }
    }

    private function debugCrawlUrl(string $url): void
    {
        if ($this->debug) {
            echo $this->counter.'/'.\count($this->urls).'    '.$this->config->getBase().$url.\PHP_EOL;
        }
    }

    private function debugInitCrawlLoop(): void
    {
        if ($this->debug) {
            echo \PHP_EOL.\PHP_EOL.'// -----'.\PHP_EOL.'// '.$this->counter.' crawled / '
                        .\count($this->urls).' found '.\PHP_EOL.'// -----'.\PHP_EOL;
        }
    }
}
