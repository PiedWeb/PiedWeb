<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Google\HtmlCacheCodec;

use function Safe\gzcompress;

final class HtmlCacheCodecTest extends TestCase
{
    private const HTML = '<html><head><style>.a{color:red}</style>'
        .'<script>var tracked=1;</script></head><body>'
        .'<noscript>enable js</noscript>'
        .'<div class="result">ORGANIC RESULT</div>'
        .'<script>analytics()</script></body></html>';

    public function testEncodeStripsScriptsButKeepsRenderableHtml(): void
    {
        $decoded = HtmlCacheCodec::decode(HtmlCacheCodec::encode(self::HTML));

        // scripts/noscript gone (neither the viewer nor SERPExtractor need them)
        $this->assertStringNotContainsStringIgnoringCase('<script', $decoded);
        $this->assertStringNotContainsStringIgnoringCase('<noscript', $decoded);
        // display + extractable structure preserved
        $this->assertStringContainsString('<style', $decoded);
        $this->assertStringContainsString('ORGANIC RESULT', $decoded);
        $this->assertStringContainsString('<div class="result">', $decoded);
    }

    public function testDecodeReadsLegacyPlainZlibFilesUntouched(): void
    {
        // files written before this codec are full-HTML gzcompress(9): must decode verbatim
        $this->assertSame(self::HTML, HtmlCacheCodec::decode(gzcompress(self::HTML, 9)));
    }

    public function testDecodeFallsBackToRawOnUnknownInput(): void
    {
        $this->assertSame('not compressed', HtmlCacheCodec::decode('not compressed'));
    }

    public function testStrippingShrinksPayloadVersusPlainZlib(): void
    {
        $html = '<html><head><style>.a{color:red}</style></head><body>'
            .'<div class="result">ORGANIC RESULT</div>'
            .'<script>'.str_repeat('console.log("noise");', 5000).'</script></body></html>';

        $this->assertLessThan(
            strlen(gzcompress($html, 9)),
            strlen(HtmlCacheCodec::encode($html)),
        );
    }
}
