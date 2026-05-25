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

    public function testEffectiveProxyIsEmptyWithoutProxy(): void
    {
        $this->assertSame('', (new PuppeteerConnector('fr'))->effectiveProxy());
    }

    public function testEffectiveProxyFallsBackToDirectWhenProxyIsDown(): void
    {
        // A configured but unreachable proxy (closed port) must not be routed through — Chrome
        // would fail every fetch. effectiveProxy() returns '' so the browser launches direct.
        $this->assertSame('', (new PuppeteerConnector('fr', 'socks5h://127.0.0.1:1'))->effectiveProxy());
    }

    public function testChromeProxyMapsSocks5hToSocks5(): void
    {
        // Chrome's --proxy-server rejects socks5h:// (ERR_NO_SUPPORTED_PROXIES).
        $this->assertSame('socks5://1.2.3.4:1080', PuppeteerConnector::chromeProxy('socks5h://1.2.3.4:1080'));
        $this->assertSame('socks5://1.2.3.4:1080', PuppeteerConnector::chromeProxy('socks5://1.2.3.4:1080'));
        $this->assertSame('', PuppeteerConnector::chromeProxy(''));
    }

    public function testStripNetBytesMarkerCapturesTransferBytesAndStrips(): void
    {
        $connector = new PuppeteerConnector('fr');
        $method = new ReflectionMethod(PuppeteerConnector::class, 'stripNetBytesMarker');

        $body = (string) $method->invoke($connector, "<!--NETBYTES:2097152-->\n<html>serp</html>");

        $this->assertSame('<html>serp</html>', $body);
        $this->assertSame(2097152, $connector->lastTransferBytes);
    }

    public function testStripNetBytesMarkerLeavesUnmarkedOutputUntouched(): void
    {
        $connector = new PuppeteerConnector('fr');
        $method = new ReflectionMethod(PuppeteerConnector::class, 'stripNetBytesMarker');

        $this->assertSame('<html>serp</html>', $method->invoke($connector, '<html>serp</html>'));
        $this->assertSame(0, $connector->lastTransferBytes);
    }

    public function testNetBytesAndCaptchaMarkersStripInOrder(): void
    {
        // scrap.js prepends NETBYTES outermost, then CAPTCHA_SOLVED — get() strips in that order.
        $connector = new PuppeteerConnector('fr');
        $stripNet = new ReflectionMethod(PuppeteerConnector::class, 'stripNetBytesMarker');
        $stripCaptcha = new ReflectionMethod(PuppeteerConnector::class, 'stripCaptchaSolvedMarker');

        $raw = "<!--NETBYTES:500-->\n<!--CAPTCHA_SOLVED-->\n<html>serp</html>";
        $body = (string) $stripCaptcha->invoke($connector, (string) $stripNet->invoke($connector, $raw));

        $this->assertSame('<html>serp</html>', $body);
        $this->assertSame(500, $connector->lastTransferBytes);
        $this->assertTrue($connector->lastCaptchaSolved);
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
