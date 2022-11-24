<?php

namespace PiedWeb\Extractor;

use Spatie\Robots\RobotsHeaders;
use Spatie\Robots\RobotsTxt;
use Symfony\Component\DomCrawler\Crawler;

class Indexable
{
    private readonly int $indexable;

    /**
     * @var int
     */
    private const INDEXABLE = 0;

    /**
     * @var int
     */
    private const NOT_INDEXABLE_ROBOTS = 1;

    /**
     * @var int
     */
    private const NOT_INDEXABLE_HEADER = 2;

    /**
     * @var int
     */
    private const NOT_INDEXABLE_META = 3;

    /**
     * @var int
     */
    private const NOT_INDEXABLE_CANONICAL = 4;

    /**
     * @var int
     */
    private const NOT_INDEXABLE_4XX = 5;

    /**
     * @var int
     */
    private const NOT_INDEXABLE_5XX = 6;

    /**
     * @var int
     */
    final public const NOT_INDEXABLE_REDIR = 9;

    public function __construct(
        private readonly Url $url,
        private readonly RobotsTxt $robotsTxt,
        private readonly Crawler $crawler,
        private readonly int $statusCode,
        private readonly string $headers,
        private readonly string $isIndexableFor = 'googlebot'
    ) {
        $this->indexable = $this->analyze();
    }

    public function robotsTxtAllows(): bool
    {
        return $this->robotsTxt->allows($this->url->__toString(), $this->isIndexableFor);
    }

    public function metaAllows(): bool
    {
        $meta = (new MetaExtractor($this->crawler))->get($this->isIndexableFor) ?? '';
        $generic = (new MetaExtractor($this->crawler))->get('robots') ?? '';

        return ! str_contains($meta, 'noindex') && ! str_contains($generic, 'noindex');
    }

    public function headersAllow(): bool
    {
        return RobotsHeaders::create(explode(\PHP_EOL, $this->headers))
            ->mayIndex($this->isIndexableFor);
    }

    public function isIndexable(): bool
    {
        return 0 === $this->indexable;
    }

    public function getIndexableStatus(): int
    {
        return $this->indexable;
    }

    public function getErrorMessage(): string
    {
        $class = new \ReflectionClass(self::class);
        $constants = array_flip($class->getConstants()); // @phpstan-ignore-line

        return $constants[$this->indexable];
    }

    private function analyze(): int
    {
        if ($this->robotsTxtAllows()) {
            return self::NOT_INDEXABLE_ROBOTS;
        }

        if ($this->headersAllow()) {
            return self::NOT_INDEXABLE_HEADER;
        }

        if (! $this->metaAllows()) {
            return self::NOT_INDEXABLE_META;
        }

        // canonical
        if (! (new CanonicalExtractor($this->url, $this->crawler))->isCanonicalCorrect()) {
            return self::NOT_INDEXABLE_CANONICAL;
        }

        // status 4XX
        if ($this->statusCode < 500 && $this->statusCode > 399) {
            return self::NOT_INDEXABLE_4XX;
        }

        // status 5XX
        if ($this->statusCode < 600 && $this->statusCode > 499) {
            return self::NOT_INDEXABLE_5XX;
        }

        // status 3XX
        if ($this->statusCode < 400 && $this->statusCode > 299) {
            return self::NOT_INDEXABLE_REDIR;
        }

        return self::INDEXABLE;
    }
}
