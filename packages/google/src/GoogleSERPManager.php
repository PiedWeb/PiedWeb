<?php

namespace PiedWeb\Google;

use DateTime;
use Symfony\Component\Filesystem\Filesystem;

final class GoogleSERPManager
{
    /** @var string Contain the string query we will ask to Google Search * */
    public string $q = '';

    /** @var string Contain the Google TLD we want to query * */
    public string $tld = 'fr';

    /** @var string Contain the language we want to send via HTTP Header Accept-Language (language[-local], eg. : en-US) * */
    public string $language = 'fr';

    /** @var array<string, string> Google Search URLs parameters (Eg. : hl => en, num => 100) * */
    public array $parameters = [];

    /** @var string Contain http proxy settings * */
    public string $proxy = '';

    /** @var array<string, string> see emulate options for puppeteer * */
    public array $emulateOptions = [];

    /** @var int Contain in seconds, the time cache is valid. Default 1 Day (86400). * */
    public int $cacheTime = 86400;

    public int $cacheFilemtime = 0;

    public Sleeper $sleeper;

    public int $page = 1;

    public function setParameter(string $k, string|int $v): void
    {
        $this->parameters[$k] = (string) $v;
    }

    public function setWaitBetween2Request(int $averageSleepTimeInseconds): void
    {
        $this->sleeper = new Sleeper($averageSleepTimeInseconds);
    }

    public function generateGoogleSearchUrl(): string
    {
        if ('' !== $this->q) {
            $this->setParameter('q', $this->q);
        }

        $url = 'https://www.google.'.$this->tld.'/search?'.$this->generateParameters();

        return $url;
    }

    private function generateParameters(): string
    {
        return http_build_query($this->parameters, '', '&');
    }

    /** @var string Contain the cache folder for SERP results * */
    public string $cacheFolder = '/tmp';

    public function getCacheFilePath(): string
    {
        $this->generateGoogleSearchUrl();

        return $this->cacheFolder.'/gsc_'.sha1(\Safe\json_encode($this)).'.html';
    }

    public function deleteCache(): void
    {
        @unlink($this->getCacheFilePath());
    }

    public function setCache(string $html): string
    {
        if ('' !== $this->cacheFolder) {
            (new Filesystem())->dumpFile($this->getCacheFilePath(), $html);
        }

        return $html;
    }

    public function getCache(): ?string
    {
        $cacheFilePath = $this->getCacheFilePath();

        if (! file_exists($cacheFilePath)) {
            return null;
        }

        $this->cacheFilemtime = \Safe\filemtime($cacheFilePath);
        $diff = time() - $this->cacheFilemtime;
        if ($diff > $this->cacheTime) {
            return null;
        }

        return \Safe\file_get_contents($cacheFilePath);
    }

    public function getExtractedAt(): int
    {
        if (0 === $this->cacheFilemtime) {
            return (int) (new \DateTime('now'))->format('ymdHi');
        }

        return (int) (new DateTime())->setTimestamp($this->cacheFilemtime)->format('ymdHi');
    }
}
