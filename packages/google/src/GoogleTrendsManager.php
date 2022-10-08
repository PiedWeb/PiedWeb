<?php

namespace PiedWeb\Google;

use Exception;
use PiedWeb\Google\Extractor\TrendsExtractor;

final class GoogleTrendsManager
{
    use CacheTrait;

    public string $language = 'fr';

    public string $geo = 'FR';

    public ?TrendsExtractor $extractor = null;

    public GoogleRequester $requester;

    public function getRequestUid(): string
    {
        return substr(sha1($this->q.'++'.$this->language.'++'.$this->geo), 0, 8);
    }

    public function __construct(public string $q, ?callable $manageProxy)
    {
        $this->requester = new GoogleRequester();
        $this->requester->manageProxy = $manageProxy;
    }

    public function getGoogleTrendsUrl(): string
    {
        return filter_var($this->q, \FILTER_VALIDATE_URL)
            ? $this->q
            : 'https://trends.google.com/trends/explore?hl=fr&q='.urlencode($this->q).'&geo='.$this->geo;
    }

    public function getExtractor(): TrendsExtractor
    {
        $cache = $this->getCache();
        if (null === $cache) {
            return $this->getExtractorFromLive();
        }

        $extractor = new TrendsExtractor();

        /** @var array{0: object, 1:object, 2:object, 3:object} */
        $toExtract = \Safe\json_decode($cache);
        foreach ($toExtract[0]->widgets as $widget) { // @phpstan-ignore-line
            if ('RELATED_TOPICS' === $widget->id) {
                $extractor->setRelatedTopics($toExtract[1]);
            }

            if ('RELATED_QUERIES' === $widget->id) {
                $extractor->setRelatedQueries($toExtract[2]);
            }

            if ('TIMESERIES' === $widget->id) {
                $extractor->setInterestOverTime($toExtract[3]);
            }
        }

        return $extractor;
    }

    public function getExtractorFromLive(): TrendsExtractor
    {
        $extractor = new TrendsExtractor();

        $jsonResponse = $this->requester->requestTrendsApi('/trends/api/explore', [
            'hl' => 'fr',
            'req' => \Safe\json_encode([
                'comparisonItem' => [
                    [
                        'keyword' => $this->q,
                        'geo' => $this->geo,
                        'time' => 'today 12-m',
                    ],
                ],
                'category' => 0,
                'property' => '',
            ]),
            'tz' => '-120',
        ]);

        if (! property_exists($jsonResponse, 'widgets')) {
            throw new Exception();
        }

        foreach ($jsonResponse->widgets as $widget) {
            if ('RELATED_TOPICS' === $widget->id) {
                $extractor->setRelatedTopics($this->requestWidget('/trends/api/widgetdata/relatedsearches', $widget->token, $widget->request));
            }

            if ('RELATED_QUERIES' === $widget->id) {
                $extractor->setRelatedQueries($this->requestWidget('/trends/api/widgetdata/relatedsearches', $widget->token, $widget->request));
            }

            if ('TIMESERIES' === $widget->id) {
                $extractor->setInterestOverTime($this->requestWidget('/trends/api/widgetdata/multiline', $widget->token, $widget->request));
            }
        }

        $this->setCache(\Safe\json_encode([$jsonResponse, $extractor->relatedTopics, $extractor->relatedQueries, $extractor->interestOverTime]));

        return $extractor;
    }

    private function requestWidget(string $uri, string $token, mixed $req): object
    {
        return $this->requester->requestTrendsApi($uri, [
            'hl' => 'fr',
            'req' => \Safe\json_encode($req),
            'token' => $token,
            'tz' => '-120',
        ], 5);
    }
}
