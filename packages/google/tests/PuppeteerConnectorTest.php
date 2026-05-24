<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\Puppeteer\PuppeteerConnector;

final class PuppeteerConnectorTest extends TestCase
{
    public function testResolveExitIpIsEmptyWithoutProxy(): void
    {
        $this->assertSame('', (new PuppeteerConnector('fr'))->resolveExitIp());
    }

    public function testResolveExitIpIsEmptyWhenProbeFails(): void
    {
        // Dead proxy (closed port) → probe fails fast and must degrade to no IP-keying,
        // not throw, so the caller falls back to the inherited profile.
        $this->assertSame('', (new PuppeteerConnector('fr', 'socks5h://127.0.0.1:1'))->resolveExitIp());
    }

    public function testExitProfileBaseDefaultAndOverride(): void
    {
        $method = (new ReflectionMethod(PuppeteerConnector::class, 'exitProfileBase'));
        $connector = new PuppeteerConnector('fr');

        unset($_SERVER['PUPPETEER_EXIT_PROFILE_BASE']);
        $this->assertStringContainsString('pp-exit-profiles', $method->invoke($connector));

        $_SERVER['PUPPETEER_EXIT_PROFILE_BASE'] = '/var/data/exit-profiles';
        $this->assertSame('/var/data/exit-profiles', $method->invoke($connector));
        unset($_SERVER['PUPPETEER_EXIT_PROFILE_BASE']);
    }
}
