<?php

namespace PiedWeb\Crawler;

use PiedWeb\Extractor\RobotsTxtExtractor;
use PiedWeb\Extractor\Url as UrlManipuler;
use Spatie\Robots\RobotsTxt;

final class CrawlerConfig
{
    private ?string $id = null;

    private UrlManipuler $startUrl;

    private ?RobotsTxt $robotsTxt = null;

    private ?RobotsTxt $virtualRobots = null;

    private ?Recorder $recorder = null;

    private ?RecordPlayer $recordPlayer = null;

    private ?string $base = null;

    public readonly string $dataDirectory;

    /**
     * @param string[] $toHarvest
     *                            param array<string, int|string|bool> $params
     */
    public function __construct(
        public readonly int $depthLimit = 0,
        public readonly string $userAgent = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        public readonly int $cacheMethod = Recorder::CACHE_URI,
        public readonly int $sleepBetweenReqInMs = 10000, // microseconds
        public readonly string $virtualRobotsTxtRules = '',
        public readonly bool $executeJs = false,
        public readonly array $toHarvest = [
            'indexable',
            'links',
            'textData',
            'title',
            'h1',
            'canonical',
        ],
        string $dataDirectory = '',
        public readonly int $autosave = 500 // number of Urls we can crawled before saving (0 = autosaving disabled)
    ) {
        $this->dataDirectory = self::dataDirectory($dataDirectory);
    }

    public function setStartUrl(string $startUrl): self
    {
        $this->startUrl = new UrlManipuler($startUrl);
        $this->id ??= date('ymdHi').'-'.$this->startUrl->getHost();

        return $this;
    }

    public function getStartUrl(): UrlManipuler
    {
        return $this->startUrl;
    }

    public static function dataDirectory(?string $dataDirectory = null): string
    {
        $dataDirectory = (string) $dataDirectory;

        return rtrim('' !== $dataDirectory ? $dataDirectory : __DIR__.'/../data', '/');
    }

    /**
     * @return string id
     */
    public static function getLastCrawl(string $dataDirectory): string
    {
        $dir = \Safe\scandir($dataDirectory);
        $lastCrawl = null;
        $lastRunAt = null;

        foreach ($dir as $file) {
            if ('.' != $file && '..' != $file
                && is_dir($dataDirectory.'/'.$file)
                && filemtime($dataDirectory.'/'.$file) > $lastRunAt) {
                $lastCrawl = $file;
                $lastRunAt = filemtime($dataDirectory.'/'.$file);
            }
        }

        if (null === $lastCrawl) {
            throw new \Exception('No crawl previously runned');
        }

        return $lastCrawl;
    }

    public static function loadFrom(string $crawlId, ?string $dataDirectory = null): self
    {
        $dataDirectory = self::dataDirectory($dataDirectory);

        if ('last' === $crawlId) {
            $crawlId = self::getLastCrawl(rtrim(self::getDataFolderFrom('', $dataDirectory), '/'));
        }

        $configFilePath = self::getDataFolderFrom($crawlId, $dataDirectory).'/config.json';
        if (! file_exists($configFilePath)) {
            throw new \Exception('Crawl `'.$crawlId.'` not found ('.$configFilePath.').');
        }

        $config = \Safe\json_decode(file_get_contents($configFilePath), true); // @phpstan-ignore-line

        return (new self(
            $config[2], // @phpstan-ignore-line
            $config[3], // @phpstan-ignore-line
            $config[4],  // @phpstan-ignore-line
            $config[5], // @phpstan-ignore-line
            $config[6], // @phpstan-ignore-line
            $config[7], // @phpstan-ignore-line
            $config[8], // @phpstan-ignore-line
            $dataDirectory
        ))->setStartUrl(\strval($config[1]))// @phpstan-ignore-line
            ->setId($config[0]); // @phpstan-ignore-line
    }

    public function recordConfig(): void
    {
        $this->getRecorder(); // permit to create folder
        file_put_contents($this->getDataFolder().'/config.json', \Safe\json_encode([
            $this->id,
            $this->getStartUrl()->get(),
            $this->depthLimit,
            $this->userAgent,
            $this->cacheMethod,
            $this->sleepBetweenReqInMs,
            $this->virtualRobotsTxtRules,
            $this->executeJs,
            $this->toHarvest,
        ]));
    }

    private static function getDataFolderFrom(string $id, ?string $path): string
    {
        return ($path ?? __DIR__.'/../data').'/'.$id;
    }

    public function getDataFolder(): string
    {
        return $this->dataDirectory.'/'.$this->id;
    }

    public function getVirtualRobots(): RobotsTxt
    {
        if (null === $this->virtualRobots) {
            $this->virtualRobots = new RobotsTxt($this->virtualRobotsTxtRules);
        }

        return $this->virtualRobots;
    }

    private function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id ?? throw new \Exception('id is not setted');
    }

    public function getBase(): string
    {
        return $this->base ??= preg_match('@^(http://|https://)?[^/\?#]+@', $url = $this->startUrl->get(), $match) ? $match[0] : $url;
    }

    public function getUrl(Url $url): UrlManipuler
    {
        return new UrlManipuler($this->getBase().$url->getUri());
    }

    public function getRobotsTxt(): RobotsTxt
    {
        if (null === $this->robotsTxt) {
            $this->robotsTxt = (new RobotsTxtExtractor())->get($this->startUrl);
        }

        return $this->robotsTxt;
    }

    public function getRecorder(): Recorder
    {
        if (null === $this->recorder) {
            $this->recorder = new Recorder($this->getDataFolder(), $this->cacheMethod);
        }

        return $this->recorder;
    }

    public function getRecordPlayer(): RecordPlayer
    {
        if (null === $this->recordPlayer) {
            $this->recordPlayer = new RecordPlayer($this);
        }

        return $this->recordPlayer;
    }
}
