<?php

namespace PiedWeb\Google;

use Exception;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\Helper\Puphpeteer;

class GoogleRequester
{
    public ?ExtendedClient $client = null;

    public ?Puphpeteer $puppeteerClient = null;

    private bool $firstTrendsRequest = true;

    /** @var ?callable */
    public mixed $manageProxy = null;

    public function getCurlClient(): ExtendedClient
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
            throw new Exception($this->getCurlClient()->getErrorMessage());
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

        $rawHtml = $this->getPuppeteerClient()->get($manager->generateGoogleSearchUrl());

        return $rawHtml;
    }

    public function requestGoogleTrendsWithPuppeteer(GoogleTrendsManager $manager, ?callable $manageProxy = null): string
    {
        $this->getPuppeteerClient()
            ->instantiate(Puphpeteer::EMULATE_OPTIONS_MOBILE, $manager->language);

        if (true === $this->firstTrendsRequest) {
            // load Google.com cookie to avoid
            $this->getPuppeteerClient()->get('https://trends.google.com/');
            $this->firstTrendsRequest = false;
        }

        if (null !== $manageProxy) {
            \call_user_func($manageProxy, $this->getPuppeteerClient());
        }

        // add page.on ( stock xhr request in js variabile)
        $this->getPuppeteerClient()->get($manager->getGoogleTrendsUrl());
        sleep(2);
        $html = $this->getPuppeteerClient()->getBrowserPage()->content();
        // $this->getPuppeteerClient()->getBrowserPage()->screenshot(['path' => 'debug2.png']);

        // retrieve js variable to get the xhr from multiline, searchrelated...

        return $html;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function requestTrendsApi(string $uri, array $parameters, int $slice = 4): object
    {
        // $client = $this->getPuppeteerClient()
        //    ->instantiate(Puphpeteer::EMULATE_OPTIONS_MOBILE, $manager->language);

        $curlClient = $this->getCurlClient()->setLanguage('FR-fr'.';q=0.9');

        if (null !== $this->manageProxy) {
            \call_user_func($this->manageProxy, $this->getCurlClient());
        }

        if (true === $this->firstTrendsRequest) {
            $curlClient->request('https://trends.google.com/trends/?geo=FR'); // load cookies
            $this->firstTrendsRequest = false;
        }

        $curlClient->request('https://trends.google.com'.$uri.'?'.http_build_query($parameters));
        $response = $curlClient->getResponse()->getBody();

        /** @var object|false */
        $jsonResponse = json_decode(substr($response, $slice), null, 512, \JSON_THROW_ON_ERROR);

        if (! $jsonResponse) {
            file_put_contents('/tmp/debug.html', $response);

            throw new Exception('Google Trends Api Request to `'.$uri.'` failed... see /tmp/debug.html');
        }

        return $jsonResponse;
    }
}
