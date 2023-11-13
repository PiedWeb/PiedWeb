<?php

namespace PiedWeb\Extractor;

use Spatie\Robots\RobotsHeaders;
use Spatie\Robots\RobotsTxt;
use Symfony\Component\DomCrawler\Crawler;

final class Indexable
{
    private readonly int $indexable;

    /**
     * @var array<string, int>
     */
    public const NOT_INDEXABLE = [
        'robots' => 1,
        'header' => 2,
        'meta' => 3,
        'canonical' => 4,
        '4XX' => 5,
        '5XX' => 6,
        'redir' => 7,
    ];

    public static function notIndexableLabel(int $code): string
    {
        $labels = array_flip(self::NOT_INDEXABLE);

        return $labels[$code] ?? 'unknow';
    }

    /**
     * @var int
     */
    public const INDEXABLE = 0;

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
        if (! $this->robotsTxtAllows()) {
            return self::NOT_INDEXABLE['robots'];
        }

        if (! $this->headersAllow()) {
            return self::NOT_INDEXABLE['header'];
        }

        if (! $this->metaAllows()) {
            return self::NOT_INDEXABLE['meta'];
        }

        // canonical
        $canonicalExtractor = new CanonicalExtractor($this->url, $this->crawler);
        if ($canonicalExtractor->canonicalExists() && ! $canonicalExtractor->isCanonicalCorrect()) {
            return self::NOT_INDEXABLE['canonical'];
        }

        // status 4XX
        if ($this->statusCode < 500 && $this->statusCode > 399) {
            return self::NOT_INDEXABLE['4XX'];
        }

        // status 5XX
        if ($this->statusCode < 600 && $this->statusCode > 499) {
            return self::NOT_INDEXABLE['5XX'];
        }

        // status 3XX
        if ($this->statusCode >= 400) {
            // weird
            return self::INDEXABLE;
        }

        if ($this->statusCode <= 299) {
            return self::INDEXABLE;
        }

        return self::NOT_INDEXABLE['redir'];
    }
}
