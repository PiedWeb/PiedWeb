<?php

namespace PiedWeb\Google;

use PiedWeb\Curl\ExtendedClient;

class GoogleSuggester
{
    /** @var string[] */
    private array $results = [];

    private readonly ExtendedClient $client;

    public function __construct(
        public string $keyword,
        public string $lang = 'fr'
    ) {
        $this->client = (new GoogleRequester())->getCurlClient();
    }

    /** @return string[] */
    public function extract(): array
    {
        $url = 'http://suggestqueries.google.com/complete/search?client=firefox&hl='.$this->lang;
        $this->extractSuggests($url.'&q='.urlencode($this->keyword));

        foreach (range('a', 'z') as $letter) {
            $this->extractSuggests($url.'&q='.urlencode($this->keyword).'+'.$letter);
        }

        return $this->results;
    }

    private function extractSuggests(string $url): void
    {
        if (! $this->client->request($url)) {
            throw new GoogleException('kicked harvesting suggests `'.$url.'`');
        }

        $content = $this->client->getResponse()->getContent();
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        if (! \is_array($data)) {
            return;
        }

        if (! isset($data[1])) {
            return;
        }

        $list = $data[1];
        if (! \is_array($list)) {
            return;
        }

        $list = array_filter($list, \is_string(...));
        /** @var list<string> $list */
        $this->results = array_merge($list, $this->results);
        $this->results = array_unique($this->results);
        usleep(500);
    }
}
