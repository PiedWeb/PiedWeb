<?php

namespace PiedWeb\Google\GoogleRequester;

use Nesk\Rialto\Data\JsFunction;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleTrendsManager;
use PiedWeb\Google\Helper\Puphpeteer;
use PiedWeb\Google\Helper\PuppeteerLogger;

class GoogleRequesterTrendsWithPuppeteer extends GoogleRequester implements GoogleRequesterTrendsInterface
{
    private bool $firstTrendsRequest = true;

    public function __construct(private readonly GoogleTrendsManager $trendsManager)
    {
    }

    /** @return array{TIMESERIES: string, RELATED_TOPICS: string, RELATED_QUERIES: string} */
    public function getData(): array
    {
        $this->requestTrendsApiWithPuppeteer();

        $toReturn = [
            'TIMESERIES' => '',
            'RELATED_TOPICS' => '',
            'RELATED_QUERIES' => '',
        ];
        $index = $this->getPuppeteerClient()->getLogger()->getIndex();

        if ([] === $index) {
            throw new \Exception('index empty');
        }

        foreach ($index as $key => $value) {
            if (str_contains($key, '/trends/api/widgetdata/relatedsearches')) {
                $toReturnKey = str_contains($value, '{"default":{"rankedList":[{"rankedKeyword":[{"query":')
                        ? 'RELATED_QUERIES' : 'RELATED_TOPICS';
                $value = trim(substr($value, 5));
                $value = '{"default":{"rankedList":[{"rankedKeyword":[]},{"rankedKeyword":[]}]}}' === $value ? '' : $value;
                $toReturn[$toReturnKey] = $value;
            }

            if (str_contains($key, '/trends/api/widgetdata/multiline')) {
                $toReturn['TIMESERIES'] = trim(substr($value, 5));
            }
        }

        $this->getPuppeteerClient()->getLogger()->resetIndex();

        return $toReturn;
    }

    private function requestTrendsApiWithPuppeteer(): void
    {
        $this->getPuppeteerClient()
            ->instantiate(Puphpeteer::EMULATE_OPTIONS_MOBILE, 'fr');

        if (null !== $this->trendsManager->manageProxy) {
            \call_user_func($this->trendsManager->manageProxy, $this->getPuppeteerClient());
        }

        if ($this->firstTrendsRequest) {
            $this->getPuppeteerClient()->get('https://trends.google.com/trends/?geo=FR'); // load cookies
            sleep(2);
            $this->firstTrendsRequest = false;
        }

        $url = 'https://trends.google.com/trends/explore?geo=FR&q='.urlencode($this->trendsManager->q).'';

        $page = $this->getPuppeteerClient()->getBrowserPage();
        $onResponseFunction = JsFunction::createWithParameters(['response']) // @phpstan-ignore-line
            ->body("
                let responseUrl = response.url();
                if (responseUrl.startsWith('https://trends.google.com/trends/api/')) {
                  response.text().then(function(body) {
                    console.log('".PuppeteerLogger::TO_INDEX."'+responseUrl+'".PuppeteerLogger::KEY_VALUE_SEPARATOR."'+body);
                  });
                }
            ")->async(true);
        $page->on('response', $onResponseFunction);
        $response = $page->goto($url)
            ?? throw new \Exception('Puppeteer return null targeting `'.$url.'`');
        $status = $response->status();
        if (200 !== (int) $status) {
            throw new \Exception(429 === (int) $status ? 'Google blocked current IP requesting Trends' : (string) $status);
        }

        sleep(5);
        $page->screenshot(['path' => 'debug.png']);
    }
}
