<?php

namespace PiedWeb\Google;

use function Safe\gzcompress;

/**
 * Codec for the SERP HTML cache files (SearchResultsGoogleCache/{slug}).
 *
 * Write: strip <script>/<noscript> — never needed by the "Voir le cache" viewer (it strips them
 * at render) nor by SERPExtractor (it parses the DOM via DomCrawler, not scripts) — then compress
 * with brotli-11 when ext-brotli is loaded, else zlib-9. Stripping alone cuts ~30%, +brotli ~55%.
 *
 * Read: tolerant — zlib first (its Adler-32 checksum reliably rejects non-zlib input), then brotli,
 * then raw. Legacy zlib files keep working and the codec migrates lazily (no forced rewrite).
 */
final class HtmlCacheCodec
{
    private const STRIP_SCRIPT = '#<script\b[^<]*(?:(?!</script>)<[^<]*)*</script>#i';

    private const STRIP_NOSCRIPT = '#<noscript\b[^<]*(?:(?!</noscript>)<[^<]*)*</noscript>#i';

    public static function encode(string $html): string
    {
        $stripped = preg_replace([self::STRIP_SCRIPT, self::STRIP_NOSCRIPT], '', $html);
        if (! \is_string($stripped)) {
            $stripped = $html;
        }

        if (\function_exists('brotli_compress')) {
            $brotli = brotli_compress($stripped, 11); // @phpstan-ignore-line ext-brotli is optional
            if (\is_string($brotli) && '' !== $brotli) {
                return $brotli;
            }
        }

        return gzcompress($stripped, 9);
    }

    public static function decode(string $raw): string
    {
        $zlib = @gzuncompress($raw);
        if (\is_string($zlib)) {
            return $zlib;
        }

        if (\function_exists('brotli_uncompress')) {
            $brotli = @brotli_uncompress($raw); // @phpstan-ignore-line ext-brotli is optional
            if (\is_string($brotli)) {
                return $brotli;
            }
        }

        return $raw;
    }
}
