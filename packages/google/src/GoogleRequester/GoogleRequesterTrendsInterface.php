<?php

namespace PiedWeb\Google\GoogleRequester;

interface GoogleRequesterTrendsInterface
{
    /** @return array{TIMESERIES: string, RELATED_TOPICS: string, RELATED_QUERIES: string} */
    public function getData(): array;
}
