<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\GoogleTrendsManager;

final class GoogleTrendsTest extends TestCase
{
    public function testTrends()
    {
        $manager = new GoogleTrendsManager('randonnée');
        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getVolumeAverage());

        // dump($extractor->getVolumeAverage());
        // dump($extractor->getRelatedQueries());
        // dump($extractor->getRelatedTopics());

        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getVolumeAverage());
        $this->assertArrayHasKey('randonnée vtt', $extractor->getRelatedQueriesSimplified());
    }
}
