<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\GoogleSuggester;

final class GoogleSuggesterTest extends TestCase
{
    public function testGoogleSuggester(): void
    {
        $suggester = new GoogleSuggester('pizza');
        // dump($suggester->extract());
        $this->assertGreaterThan(10, count($suggester->extract()));
    }
}
