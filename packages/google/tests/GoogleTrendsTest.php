<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\GoogleTrendsManager;

final class GoogleTrendsTest extends TestCase
{
    public function testTrendsPuppet(): void
    {
        $manager = new GoogleTrendsManager('a big unknow keyword not know in google database2');
        $manager->disableCache = true;
        $extractor = $manager->getExtractor();
        $this->assertSame(1, $extractor->getInterestAverage());

        $manager = new GoogleTrendsManager('randonnée');
        $manager->disableCache = true;
        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getInterestAverage());

        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getInterestAverage());
        dump($extractor->getRelatedQueriesSimplified());
        $this->assertArrayHasKey('randonnée pédestre', $extractor->getRelatedQueriesSimplified());
    }
}
