<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\GoogleRequester\GoogleRequesterTrendsWithCurl;
use PiedWeb\Google\GoogleTrendsManager;

final class GoogleTrendsTest extends TestCase
{
    public function testTrendsCurl(): void
    {
        $manager = new GoogleTrendsManager('randonnée', null, GoogleRequesterTrendsWithCurl::class);
        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getVolumeAverage());

        // dump($extractor->getVolumeAverage());
        // dump($extractor->getRelatedQueries());
        // dump($extractor->getRelatedTopics());

        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getVolumeAverage());
        $this->assertArrayHasKey('randonnée vtt', $extractor->getRelatedQueriesSimplified());
    }

    public function testTrendsPuppet(): void
    {
        $manager = new GoogleTrendsManager('a big unknow keyword not know in google database2');
        $extractor = $manager->getExtractor();
        $this->assertSame(1, $extractor->getVolumeAverage());

        $manager = new GoogleTrendsManager('randonnée');
        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getVolumeAverage());

        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getVolumeAverage());
        $this->assertArrayHasKey('randonnée vtt', $extractor->getRelatedQueriesSimplified());
    }
}
