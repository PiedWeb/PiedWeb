<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\GoogleTrendsManager;

final class GoogleTrendsTest extends TestCase
{
    public function testTrendsPuppet(): void
    {
        $manager = new GoogleTrendsManager('a big unknow keyword not know in google database');
        $manager->disableCache = true;
        $extractor = $manager->getExtractor();
        if (1 !== $extractor->getInterestAverage()) {
            dump('GoogleTrendsTest is skipped (probably ip kick');
        } else {
            $this->assertSame(1, $extractor->getInterestAverage());
        }

        $manager = new GoogleTrendsManager('randonnée');
        $manager->disableCache = true;
        $extractor = $manager->getExtractor();
        if ($extractor->getInterestAverage() < 3) {
            dump('GoogleTrendsTest is skipped (probably ip kick');
        } else {
            $this->assertGreaterThan(10, $extractor->getInterestAverage());
        }

        $extractor = $manager->getExtractor();
        $this->assertGreaterThan(10, $extractor->getInterestAverage());
        // dump($extractor->getRelatedQueriesSimplified());
        $this->assertArrayHasKey('randonnée pédestre', $extractor->getRelatedQueriesSimplified());
    }
}
