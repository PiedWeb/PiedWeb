<?php

namespace PiedWeb\Crawler;

use PiedWeb\Extractor\RobotsTxtExtractor;
use PiedWeb\Extractor\Url as UrlManipuler;
use Spatie\Robots\RobotsTxt;

final class CrawlerConfig
{
    private ?string $id = null;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private UrlManipuler $startUrl;

    public ?RobotsTxt $robotsTxt = null;

    private ?RobotsTxt $virtualRobots = null;

    private ?Recorder $recorder = null;

    private ?RecordPlayer $recordPlayer = null;

    private ?string $base = null;

    public readonly string $dataDirectory;

    public bool $executeJs = false;

    /**
     * @param string[] $toHarvest
     */
    public function __construct(
        public readonly int $depthLimit = 0,
        public readonly string $userAgent = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        public readonly int $cacheMethod = Recorder::CACHE_URI,
        public readonly int $sleepBetweenReqInMs = 0, // ms
        public readonly string $virtualRobotsTxtRules = '',
        public readonly array $toHarvest = [
            'indexable',
            'links',
            'textData',
            'title',
            'h1',
            'canonical',
            'hrefLang',
            'socialProfiles',
        ],
        ?string $dataDirectory = null,
        public readonly int $autosave = 500, // number of Urls we can crawled before saving (0 = autosaving disabled),
        public readonly string $userPassword = ''
    ) {
        $this->dataDirectory = self::dataDirectory((string) $dataDirectory);
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
        /** @var list<string> */
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
            // $config[6], // executeJs
            $config[7], // @phpstan-ignore-line
            $config[8], // @phpstan-ignore-line
            $dataDirectory
        ))->setStartUrl((string) $config[1])// @phpstan-ignore-line
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
        return $this->dataDirectory.'/'.($this->id ?? throw new \Exception());
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
        return $this->base ??= preg_match('#^(http://|https://)?[^/\?\#]+#', $url = $this->startUrl->get(), $match) ? $match[0] : $url;
    }

    public function isSameHostThanStartUrl(string $url): bool
    {
        return str_starts_with($url, $this->getBase());
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
            $this->recorder = Recorder::RECORD_NOTHING === $this->cacheMethod ? new RecorderNothing()
                : new Recorder($this->getDataFolder(), $this->cacheMethod);
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
