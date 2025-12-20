<?php

namespace PiedWeb\Extractor;

use Spatie\Robots\RobotsHeaders;
use Spatie\Robots\RobotsTxt;
use Symfony\Component\DomCrawler\Crawler;

final class Indexable
{
    private readonly int $indexable;

    /** @var int[] */
    private readonly array $indexableList;

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
        $this->indexableList = $this->analyze();
        $this->indexable = $this->indexableList[0] ?? self::INDEXABLE;
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

    /**
     * @return int[]
     */
    public function getIndexableList(): array
    {
        return $this->indexableList;
    }

    public function getErrorMessage(): string
    {
        $class = new \ReflectionClass(self::class);
        $constants = array_flip($class->getConstants()); // @phpstan-ignore-line

        return $constants[$this->indexable];
    }

    /**
     * @return int[]
     */
    private function analyze(): array
    {
        $reasons = [];

        if (! $this->robotsTxtAllows()) {
            $reasons[] = self::NOT_INDEXABLE['robots'];
        }

        if (! $this->headersAllow()) {
            $reasons[] = self::NOT_INDEXABLE['header'];
        }

        if (! $this->metaAllows()) {
            $reasons[] = self::NOT_INDEXABLE['meta'];
        }

        $canonicalExtractor = new CanonicalExtractor($this->url, $this->crawler);
        if ($canonicalExtractor->canonicalExists() && ! $canonicalExtractor->isCanonicalCorrect()) {
            $reasons[] = self::NOT_INDEXABLE['canonical'];
        }

        if ($this->statusCode < 500 && $this->statusCode > 399) {
            $reasons[] = self::NOT_INDEXABLE['4XX'];
        }

        if ($this->statusCode < 600 && $this->statusCode > 499) {
            $reasons[] = self::NOT_INDEXABLE['5XX'];
        }

        if ($this->statusCode > 299 && $this->statusCode < 400) {
            $reasons[] = self::NOT_INDEXABLE['redir'];
        }

        return $reasons;
    }
}
