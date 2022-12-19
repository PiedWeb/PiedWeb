<?php

/**
 * Wrapper for League\Uri.
 *
 * Permits to cache registrableDomain and Origin
 */

namespace PiedWeb\Extractor;

final class UrlNormalizer
{
    /**
     * Add trailing slash for domain. Eg: https://piedweb.com => https://piedweb.com/ and '/test ' = '/test'.
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ('' == preg_replace('#(.*\://?([^\/]+))#', '', $url)) {
            $url .= '/';
        }

        return $url;
    }
}
