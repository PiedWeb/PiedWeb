<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\GoogleSERPManager;
use PiedWeb\Google\Helper\Puphpeteer;
use PiedWeb\Google\Extractor\PuphpeteerExtractor;
use PiedWeb\Google\Extractor\SERPExtractor;

final class PuphpeteerProviderTest extends TestCase
{

    private function getSerpManager()
    {
        $manager = new GoogleSERPManager();
        $manager->language = 'fr-FR';
        $manager->tld = 'fr';
        $manager->q = 'pied web';
        //$manager->setParameter('num', '100');
        $manager->generateGoogleSearchUrl();
        return $manager;
    }

    private function extractSERP(string $rawHtml)
    {
        $extractor = new SERPExtractor($rawHtml);
        //$this->assertNotSame(0, $extractor->getNbrResults());
        $this->assertSame('https://piedweb.com/', $extractor->getResults()[0]->url);

    }

    public function testCurl(): void
    {
        $manager = $this->getSerpManager();

        $curlClient = new ExtendedClient($manager->generateGoogleSearchUrl());
        $curlClient
            ->setDesktopUserAgent()
            ->setDefaultSpeedOptions()
            ->setCookie('CONSENT=YES+')
            ->setLanguage($manager->language.';q=0.9')
            ->fakeBrowserHeader()
            ->request();
        if ($curlClient->getError() !== 0)
            throw new Exception($curlClient->getErrorMessage());
        $rawHtml = $curlClient->getResponse()->getBody();
        $this->assertStringContainsString('piedweb.com', $rawHtml);

        $this->extractSERP($rawHtml);

    }

    public function testPuphpeteer(): void
    {
        $manager = $this->getSerpManager();

        $PuphpeteerClient = new Puphpeteer();
        $PuphpeteerClient->instantiate(Puphpeteer::EMULATE_OPTIONS_DESKTOP, $manager->language);
        $PuphpeteerClient->setCookie('CONSENT', 'YES+', '.google.fr');
        $rawHtml = $PuphpeteerClient->get($manager->generateGoogleSearchUrl());
        file_put_contents('debug.html', $rawHtml);
        $PuphpeteerClient->getBrowserPage()->screenshot(['path' => 'debug.png']);

        $this->extractSERP($rawHtml);

    }


    public function testPuphpeteerMobile(): void
    {
        $manager = $this->getSerpManager();

        $PuphpeteerClient = new Puphpeteer();
        $PuphpeteerClient->instantiate([], $manager->language);
        $PuphpeteerClient->setCookie('CONSENT', 'YES+', '.google.fr');
        $rawHtml = $PuphpeteerClient->get($manager->generateGoogleSearchUrl());
        file_put_contents('debug.html', $rawHtml);
        $PuphpeteerClient->getBrowserPage()->screenshot(['path' => 'debug.png']);

        $this->extractSERP($rawHtml);

    }
    public function testCurlMobile(): void
    {
        $manager = $this->getSerpManager();

        $curlClient = new ExtendedClient($manager->generateGoogleSearchUrl());
        $curlClient
            ->setMobileUserAgent()
            ->setDefaultSpeedOptions()
            ->setCookie('CONSENT=YES+')
            ->setLanguage($manager->language.';q=0.9')
            ->fakeBrowserHeader()
            ->request();
        if ($curlClient->getError() !== 0)
            throw new Exception($curlClient->getErrorMessage());
        $rawHtml = $curlClient->getResponse()->getBody();

        $this->extractSERP($rawHtml);

    }
}
