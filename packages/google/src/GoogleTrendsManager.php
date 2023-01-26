<?php

namespace PiedWeb\Google;

use PiedWeb\Google\Extractor\TrendsExtractor;
use PiedWeb\Google\GoogleRequester\GoogleRequesterTrendsInterface;
use PiedWeb\Google\GoogleRequester\GoogleRequesterTrendsWithPuppeteer;

final class GoogleTrendsManager
{
    use CacheTrait;

    public string $language = 'fr';

    public string $geo = 'FR';

    public ?TrendsExtractor $extractor = null;

    public GoogleRequesterTrendsInterface $requester;

    public function getRequestUid(): string
    {
        return substr(sha1('2__'.$this->q.'++'.$this->language.'++'.$this->geo), 0, 8);
    }

    /**
     * @param ?callable                                    $manageProxy
     * @param class-string<GoogleRequesterTrendsInterface> $requester
     */
    public function __construct(
        public string $q,
        public $manageProxy = null,
        string $requester = GoogleRequesterTrendsWithPuppeteer::class
    ) {
        $this->requester = new $requester($this);
    }

    public function getExtractor(): TrendsExtractor
    {
        $cache = $this->getCache();
        /** @var array{TIMESERIES: string, RELATED_TOPICS: string, RELATED_QUERIES: string} */
        $data = null !== $cache ? \Safe\json_decode($cache, true) : $this->requester->getData();
        if (null === $cache) {
            $this->setCache(\Safe\json_encode($data));
        }

        $extractor = new TrendsExtractor();
        $extractor->setInterestOverTime($data['TIMESERIES']);
        $extractor->setRelatedTopics($data['RELATED_TOPICS']);
        $extractor->setRelatedQueries($data['RELATED_QUERIES']);

        return $extractor;
    }
}
