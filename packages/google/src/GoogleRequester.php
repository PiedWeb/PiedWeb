<?php

namespace PiedWeb\Google;

use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\Puppeteer\PuppeteerConnector;

class GoogleRequester
{
    public ?ExtendedClient $client = null;

    /** @var ?callable */
    public mixed $manageProxy = null;

    public function getCurlClient(): ExtendedClient
    {
        if (null === $this->client) {
            $this->client = new ExtendedClient();
            $this->client
                ->setMobileUserAgent()
                ->setDefaultSpeedOptions(60, 120, 20000)
                ->setCookie('CONSENT=YES+')
                ->fakeBrowserHeader();
        }

        return $this->client;
    }

    public function requestGoogleWithCurl(GoogleSERPManager $serpManager, ?callable $manageProxy = null): string
    {
        $this->getCurlClient()->setLanguage($serpManager->language.';q=0.9');

        if (null !== $manageProxy) {
            \call_user_func($manageProxy, $this->getCurlClient());
        }

        $this->getCurlClient()->request($serpManager->generateGoogleSearchUrl());

        if (0 !== $this->getCurlClient()->getError()) {
            throw new \Exception($this->getCurlClient()->getErrorMessage());
        }

        return $this->getCurlClient()->getResponse()->getBody();
    }

    public function requestGoogleWithPuppeteer(GoogleSERPManager $serpManager, ?callable $manageProxy = null): string
    {
        $client = new PuppeteerConnector($serpManager->language);

        if (null !== $manageProxy) {
            \call_user_func($manageProxy, $this->getCurlClient());
        }

        return $client->get($serpManager->generateGoogleSearchUrl());
    }
}
