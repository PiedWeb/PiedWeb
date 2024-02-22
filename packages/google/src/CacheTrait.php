<?php

namespace PiedWeb\Google;

use function Safe\gzuncompress;

use Symfony\Component\Filesystem\Filesystem;

trait CacheTrait
{
    public bool $disableCache = false;

    /** @var int Contain in seconds, the time cache is valid. Default 1 Day (86400). * */
    public int $cacheTime = 86400;

    public int $cacheFilemtime = 0;

    /** @var string Contain the cache folder for SERP results * */
    public string $cacheFolder = '/tmp';

    public function getCacheFilePath(): string
    {
        if (method_exists($this, 'generateGoogleSearchUrl')) { // @phpstan-ignore-line
            $this->generateGoogleSearchUrl();
        }

        $cacheKey = $this->getRequestUid();

        return $this->cacheFolder.'/gsc92_'.$cacheKey.'.html';
    }

    abstract public function getRequestUid(): string;

    public function deleteCache(): void
    {
        @unlink($this->getCacheFilePath());
    }

    public function setCache(string $html, ?string $filePath = null): string
    {
        if ('' !== $this->cacheFolder) {
            (new Filesystem())->dumpFile($filePath ?? $this->getCacheFilePath(), \Safe\gzcompress($html, 9));
        }

        return $html;
    }

    public function getCache(?string $filePath = null): ?string
    {
        if ($this->disableCache) {
            return null;
        }

        $cacheFilePath = $filePath ?? $this->getCacheFilePath();

        if (! file_exists($cacheFilePath)) {
            return null;
        }

        $this->cacheFilemtime = \Safe\filemtime($cacheFilePath);
        $diff = time() - $this->cacheFilemtime;

        if ($diff > $this->cacheTime) {
            return null;
        }

        return gzuncompress(\Safe\file_get_contents($cacheFilePath));
    }

    public function getExtractedAt(): int
    {
        if (0 === $this->cacheFilemtime) {
            return (int) (new \DateTime('now'))->format('ymdHi');
        }

        return (int) (new \DateTime())->setTimestamp($this->cacheFilemtime)->format('ymdHi');
    }
}
