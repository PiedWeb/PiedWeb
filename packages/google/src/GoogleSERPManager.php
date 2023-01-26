<?php

namespace PiedWeb\Google;

final class GoogleSERPManager
{
    use CacheTrait;

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

    public Sleeper $sleeper;

    public int $page = 1;

    public function getRequestUid(): string
    {
        return substr(sha1($this->q.'++'.$this->language.'++'.$this->tld.'++'.\Safe\json_encode([$this->parameters, $this->emulateOptions]).'++'.$this->page), 0, 8);
    }

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

        return 'https://www.google.'.$this->tld.'/search?'.$this->generateParameters();
    }

    private function generateParameters(): string
    {
        return http_build_query($this->parameters, '', '&');
    }
}
