<?php

namespace PiedWeb\Google;

use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\Helper\Puphpeteer;

class GoogleRequester
{
    public ?ExtendedClient $client = null;

    public ?Puphpeteer $puppeteerClient = null;

    /** @var ?callable */
    public mixed $manageProxy = null;

    public function getCurlClient(): ExtendedClient
    {
        if (null === $this->client) {
            $this->client = new ExtendedClient();
            $this->client
                ->setMobileUserAgent()
                ->setDefaultSpeedOptions(20, 30, 2000)
                ->setCookie('CONSENT=YES+')
                ->fakeBrowserHeader();
        }

        return $this->client;
    }

    public function getPuppeteerClient(): Puphpeteer
    {
        if (null === $this->puppeteerClient) {
            $this->puppeteerClient = new Puphpeteer();
        }

        return $this->puppeteerClient;
    }

    public function requestGoogleWithCurl(GoogleSERPManager $Google, ?callable $manageProxy = null): string
    {
        $this->getCurlClient()->setLanguage($Google->language.';q=0.9');

        if (null !== $manageProxy) {
            \call_user_func($manageProxy, $this->getCurlClient());
        }

        $this->getCurlClient()->request($Google->generateGoogleSearchUrl());

        if (0 !== $this->getCurlClient()->getError()) {
            throw new \Exception($this->getCurlClient()->getErrorMessage());
        }

        return $this->getCurlClient()->getResponse()->getBody();
    }

    public function requestGoogleWithPuppeteer(GoogleSERPManager $manager, ?callable $manageProxy = null): string
    {
        $this->getPuppeteerClient()
            ->instantiate(Puphpeteer::EMULATE_OPTIONS_MOBILE, $manager->language)
            ->setCookie('CONSENT', 'YES+', '.google.fr');

        if (null !== $manageProxy) {
            \call_user_func($manageProxy, $this->getPuppeteerClient());
        }

        return $this->getPuppeteerClient()->get($manager->generateGoogleSearchUrl());
    }
}
