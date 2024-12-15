<?php

namespace PiedWeb\Google;

final class GoogleSERPManager
{
    use CacheTrait;

    /**
     * @param array<string, scalar> $parameters
     */
    public function __construct(
        public string $q = '',
        public string $tld = 'fr',
        public string $language = 'fr', // only used at browser level, if you want to use hl, use parameters
        public array $parameters = [], // Google Search URLs parameters (Eg. : hl => en, num => 100)
        public string $proxy = '',
    ) {
    }

    public int $page = 1;

    public function getRequestUid(): string
    {
        return substr(sha1($this->q.'++'.$this->language.'++'.$this->tld.'++'.\Safe\json_encode([$this->parameters]).'++'.$this->page), 0, 8);
    }

    public function setParameter(string $k, string|int $v): void
    {
        $this->parameters[$k] = (string) $v;
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
