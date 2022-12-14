<?php

declare(strict_types=1);

namespace PiedWeb\Curl\Test;

use PiedWeb\Curl\Helper;

class HelperTest extends \PHPUnit\Framework\TestCase
{
    public function testSchemeFromProxy(): void
    {
        $proxy = '75.157.242.104:59190';
        $this->assertSame('http://', Helper::getSchemeFrom($proxy));

        $proxy = 'https://75.157.242.104:59190';
        $this->assertSame('https://', Helper::getSchemeFrom($proxy));
        $this->assertSame('75.157.242.104:59190', $proxy);
    }

    public function testCheckContentType(): void
    {
        $line = 'Content-Type: text/html; charset=utf-8';
        $expected = 'text/html';
        $this->assertTrue(Helper::checkContentType($line, $expected));
    }

    public function testCheckStatusCode(): void
    {
        $line = 'HTTP/1.1 200 OK';
        $expected = 200;
        $this->assertTrue(Helper::checkStatusCode($line, $expected));
    }
}
