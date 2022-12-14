<?php

declare(strict_types=1);

namespace PiedWeb\Curl\Test;

use PiedWeb\Curl\StaticClient as Client;

class StaticWrapperTest extends \PHPUnit\Framework\TestCase
{
    public function testStaticGet(): void
    {
        $url = 'https://dev.piedweb.com/robots.txt';
        $result = Client::request($url);
        $this->assertGreaterThan(10, \strlen($result));
    }
}
