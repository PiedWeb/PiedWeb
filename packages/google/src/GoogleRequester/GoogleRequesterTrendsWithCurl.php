<?php

namespace PiedWeb\Google\GoogleRequester;

use Exception;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleTrendsManager;

class GoogleRequesterTrendsWithCurl extends GoogleRequester implements GoogleRequesterTrendsInterface
{
    private bool $firstTrendsRequest = true;

    public function __construct(private readonly GoogleTrendsManager $trendsManager)
    {
    }

    /**
     * @param array<string, string> $parameters
     */
    private function requestTrendsApi(string $uri, array $parameters, int $slice = 4): object
    {
        $curlClient = $this->getCurlClient()->setLanguage('FR-fr;q=0.9');

        if (null !== $this->trendsManager->manageProxy) {
            \call_user_func($this->trendsManager->manageProxy, $this->getCurlClient());
        }

        if ($this->firstTrendsRequest) {
            $curlClient->request('https://trends.google.com/trends/?geo=FR'); // load cookies
            $this->firstTrendsRequest = false;
        }

        $url = 'https://trends.google.com'.$uri.'?'.$this->stringifyParameters($parameters);
        // dump($url);
        for ($i = 0; $i < 2; ++$i) {
            $curlClient->request($url, false);
            $response = $curlClient->getResponse()->getBody();

            $rawJson = trim(substr($response, $slice));

            try {
                /** @var object|false */
                $jsonResponse = json_decode($rawJson, null, 512, \JSON_THROW_ON_ERROR);
            } catch (Exception) {
                sleep(1);
            }

                if (isset($jsonResponse)) {
                    break;
                }
        }

        if (! isset($jsonResponse) || ! $jsonResponse) {
            file_put_contents('/tmp/debug.html', $response);

            throw new \Exception('Google Trends Api Request to `'.$uri.'` failed... see /tmp/debug.html');
        }

        return $jsonResponse;
    }

    /** @param array<string, string> $parameters */
    private function stringifyParameters(array $parameters): string
    {
        $toReplace = [
            'hl=fr' => 'hl=fr&tz=-120',
            '%2C' => ',',
            '%3A' => ':',
        ];

        return str_replace(array_keys($toReplace), array_values($toReplace), http_build_query($parameters));
    }

    /** @return array{TIMESERIES: string, RELATED_TOPICS: string, RELATED_QUERIES: string} */
    public function getData(): array
    {
        $jsonResponse = $this->requestTrendsApi('/trends/api/explore', [
            'hl' => 'fr',
            'req' => \Safe\json_encode([
                'comparisonItem' => [
                    [
                        'keyword' => $this->trendsManager->q,
                        'geo' => $this->trendsManager->geo,
                        'time' => 'today 12-m',
                    ],
                ],
                'category' => 0,
                'property' => '',
            ]),
            'tz' => '-120',
        ]);

        if (! property_exists($jsonResponse, 'widgets')) {
            throw new \Exception();
        }

        $toReturn = [
            'TIMESERIES' => '',
            'RELATED_TOPICS' => '',
            'RELATED_QUERIES' => '',
        ];
        foreach ($jsonResponse->widgets as $widget) {
            if ('RELATED_TOPICS' === $widget->id) {
                $toReturn['RELATED_TOPICS'] = $this->requestWidget('/trends/api/widgetdata/relatedsearches', $widget->token, $widget->request);
            }

            if ('RELATED_QUERIES' === $widget->id) {
                $toReturn['RELATED_QUERIES'] = $this->requestWidget('/trends/api/widgetdata/relatedsearches', $widget->token, $widget->request);
            }

            if ('TIMESERIES' === $widget->id) {
                $toReturn['TIMESERIES'] = $this->requestWidget('/trends/api/widgetdata/multiline', $widget->token, $widget->request);
            }
        }

        return $toReturn;
    }

    private function requestWidget(string $uri, string $token, mixed $req): string
    {
        return \Safe\json_encode($this->requestTrendsApi($uri, [
            'hl' => 'fr',
            'req' => \Safe\json_encode($req),
            'token' => $token,
            'tz' => '-120',
        ], 5));
    }
}
