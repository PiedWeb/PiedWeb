<?php

namespace PiedWeb\GoogleSpreadsheetSeoScraper;

use Exception;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\GoogleSERPManager;

trait RequestGoogleTrait
{
    public ?ExtendedClient $client = null;

    abstract protected function manageProxy(): void;

    public function getClient(): ExtendedClient
    {
        if (null === $this->client) {
            $this->client = new ExtendedClient();
            $this->client
                ->setMobileUserAgent()
                ->setDefaultSpeedOptions()
                ->setCookie('CONSENT=YES+')
                ->fakeBrowserHeader();
        }

        return $this->client;
    }

    private function requestGoogleWithCurl(GoogleSERPManager $Google): string
    {
        $this->getClient()->setLanguage($Google->language.';q=0.9');
        $this->manageProxy();

        $this->getClient()->request($Google->generateGoogleSearchUrl());
        if (0 !== $this->getClient()->getError()) {
            throw new Exception($this->getClient()->getErrorMessage());
        }

        return $this->getClient()->getResponse()->getBody();
    }
}
