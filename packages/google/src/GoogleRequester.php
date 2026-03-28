<?php

namespace PiedWeb\Google;

use PiedWeb\Google\Puppeteer\PuppeteerConnector;

class GoogleRequester
{
    /** @var ?callable */
    public mixed $manageProxy = null;

    public ?PuppeteerConnector $puppeteerClient = null;

    public function requestGoogleWithPuppeteer(GoogleSERPManager $serpManager, ?callable $manageProxy = null, int $maxPages = 5): string
    {
        $this->puppeteerClient = new PuppeteerConnector($serpManager->language);

        if (null !== $manageProxy) {
            \call_user_func($manageProxy, $this->puppeteerClient);
        }

        return $this->puppeteerClient->get($serpManager->generateGoogleSearchUrl(), $maxPages);
    }
}
