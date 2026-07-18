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

    /**
     * Invoke one of the private marker strippers. ReflectionMethod::invoke() returns mixed, and
     * casting that to string only hides the uncertainty — assert it instead, which both proves the
     * stripper returned a string and gives the callers below a known type.
     */
    private function strip(PuppeteerConnector $connector, string $stripper, string $raw): string
    {
        $stripped = (new ReflectionMethod(PuppeteerConnector::class, $stripper))->invoke($connector, $raw);
        $this->assertIsString($stripped);

        return $stripped;
    }

    public function testStripNetBytesMarkerCapturesTransferBytesAndStrips(): void
    {
        $connector = new PuppeteerConnector('fr');

        $body = $this->strip($connector, 'stripNetBytesMarker', "<!--NETBYTES:2097152-->\n<html>serp</html>");

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

        $raw = "<!--NETBYTES:500-->\n<!--CAPTCHA_SOLVED-->\n<html>serp</html>";
        $body = $this->strip($connector, 'stripCaptchaSolvedMarker', $this->strip($connector, 'stripNetBytesMarker', $raw));

        $this->assertSame('<html>serp</html>', $body);
        $this->assertSame(500, $connector->lastTransferBytes);
        $this->assertTrue($connector->lastCaptchaSolved);
    }

    public function testMarkersStripDespiteLeadingDiagnosticNoise(): void
    {
        // A stray scrap.js diagnostic line on stdout used to shift the markers off byte 0, so the
        // anchored matchers missed them and lastCaptchaSolved/lastTransferBytes stayed at 0 — the
        // captcha-solved counter never moved. Both strippers must now match past leading lines.
        $connector = new PuppeteerConnector('fr');

        $raw = " - try to solve captcha for  https://www.google.fr/search?q=x\n"
            ."<!--NETBYTES:777-->\n<!--CAPTCHA_SOLVED-->\n<html>serp</html>";
        $body = $this->strip($connector, 'stripCaptchaSolvedMarker', $this->strip($connector, 'stripNetBytesMarker', $raw));

        $this->assertSame('<html>serp</html>', $body);
        $this->assertSame(777, $connector->lastTransferBytes);
        $this->assertTrue($connector->lastCaptchaSolved);
    }

    public function testShortSerpMarkerStripsAndFlags(): void
    {
        $connector = new PuppeteerConnector('fr');

        $body = $this->strip($connector, 'stripShortSerpMarker', "<!--SHORT_SERP-->\n<html>serp</html>");

        $this->assertSame('<html>serp</html>', $body);
        $this->assertTrue($connector->lastShortSerp);
    }

    public function testShortSerpFlagStaysFalseWithoutTheMarker(): void
    {
        // The common case by far: absence of the marker must never read as truncated.
        $connector = new PuppeteerConnector('fr');

        $this->assertSame('<html>serp</html>', $this->strip($connector, 'stripShortSerpMarker', '<html>serp</html>'));
        $this->assertFalse($connector->lastShortSerp);
    }

    public function testAllThreeMarkersStripInOrder(): void
    {
        // scrap.js prepends SHORT_SERP innermost, then CAPTCHA_SOLVED, then NETBYTES outermost.
        // get() unwraps in the mirror order and must leave the document untouched.
        $connector = new PuppeteerConnector('fr');

        $raw = "<!--NETBYTES:900-->\n<!--CAPTCHA_SOLVED-->\n<!--SHORT_SERP-->\n<html>serp</html>";
        $body = $this->strip(
            $connector,
            'stripShortSerpMarker',
            $this->strip($connector, 'stripCaptchaSolvedMarker', $this->strip($connector, 'stripNetBytesMarker', $raw))
        );

        $this->assertSame('<html>serp</html>', $body);
        $this->assertSame(900, $connector->lastTransferBytes);
        $this->assertTrue($connector->lastCaptchaSolved);
        $this->assertTrue($connector->lastShortSerp);
    }

    /** A truncated capture without a captcha is the ordinary case: only SHORT_SERP is present. */
    public function testShortSerpMarkerStripsWithoutACaptchaMarker(): void
    {
        $connector = new PuppeteerConnector('fr');

        $raw = "<!--NETBYTES:120-->\n<!--SHORT_SERP-->\n<html>serp</html>";
        $body = $this->strip(
            $connector,
            'stripShortSerpMarker',
            $this->strip($connector, 'stripCaptchaSolvedMarker', $this->strip($connector, 'stripNetBytesMarker', $raw))
        );

        $this->assertSame('<html>serp</html>', $body);
        $this->assertSame(120, $connector->lastTransferBytes);
        $this->assertFalse($connector->lastCaptchaSolved);
        $this->assertTrue($connector->lastShortSerp);
    }

    public function testIsValidWsEndpointAcceptsWsUrls(): void
    {
        $method = new ReflectionMethod(PuppeteerConnector::class, 'isValidWsEndpoint');

        $this->assertTrue($method->invoke(null, 'ws://127.0.0.1:38971/devtools/browser/abc'));
        $this->assertTrue($method->invoke(null, 'wss://127.0.0.1:38971/devtools/browser/abc'));
    }

    public function testIsValidWsEndpointRejectsErrorBlobAndEmpty(): void
    {
        // launchBrowser.js writes this blob when Chrome dies mid-startup (ECONNREFUSED on the
        // devtools port). It must never be mistaken for an endpoint, so getWsEndpoint() relaunches.
        $method = new ReflectionMethod(PuppeteerConnector::class, 'isValidWsEndpoint');

        $errorBlob = "Error in launchBrowser.js: ErrorEvent {\n  Symbol(kError): Error: connect ECONNREFUSED 127.0.0.1:38971\n}";
        $this->assertFalse($method->invoke(null, $errorBlob));
        $this->assertFalse($method->invoke(null, ''));
    }

    public function testExitProfileBaseDefaultAndOverride(): void
    {
        $method = (new ReflectionMethod(PuppeteerConnector::class, 'exitProfileBase'));
        $connector = new PuppeteerConnector('fr');

        unset($_SERVER['PUPPETEER_EXIT_PROFILE_BASE']);
        $default = $method->invoke($connector);
        $this->assertIsString($default);
        $this->assertStringContainsString('pp-exit-profiles', $default);

        $_SERVER['PUPPETEER_EXIT_PROFILE_BASE'] = '/var/data/exit-profiles';
        $this->assertSame('/var/data/exit-profiles', $method->invoke($connector));
        unset($_SERVER['PUPPETEER_EXIT_PROFILE_BASE']);
    }
}
