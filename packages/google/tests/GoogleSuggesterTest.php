<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\GoogleSuggester;

final class GoogleSuggesterTest extends TestCase
{
    public function testGoogleSuggester(): void
    {
        $suggester = new GoogleSuggester('pizza');
        $this->assertContains('pizza fromage', $suggester->extract());
    }
}
