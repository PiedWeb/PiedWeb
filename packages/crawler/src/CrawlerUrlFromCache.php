<?php

namespace PiedWeb\Crawler;

use PiedWeb\Curl\ResponseFromCache;

class CrawlerUrlFromCache extends CrawlerUrl
{
    protected function request(): void
    {
        $filePath = $this->config->getRecorder()->getCacheFilePath($this->url);
        if (! file_exists($filePath)) {
            parent::request();

            return;
        }

        $cachedContent = \Safe\file_get_contents($filePath);
        if (str_starts_with($cachedContent, 'curl_error_code:')
             && 42 != substr($cachedContent, \strlen('curl_error_code:'))) {
            parent::request(); // retry if was not stopped because too big

            return;
        }

        $response = new ResponseFromCache(
            $cachedContent,
            $this->config->getBase().$this->url->getUri(),
            \Safe\json_decode(\Safe\file_get_contents($filePath.'---info'), true) // @phpstan-ignore-line
        );
        $this->setUrlDataFromResponse($response);
    }
}
