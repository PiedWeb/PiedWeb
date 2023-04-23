<?php

namespace PiedWeb\Curl;

/**
 * @see \PiedWeb\Curl\Test\HelperTest
 */
class Helper
{
    /**
     * Return scheme from proxy string and remove Scheme From proxy.
     */
    public static function getSchemeFrom(string &$proxy): string
    {
        if (! preg_match('#^([a-z0-9]*)://#', $proxy, $match)) {
            return 'http://';
        }

        $scheme = $match[1].'://';
        $proxy = substr($proxy, \strlen($scheme));

        return $scheme;
    }

    /**
     * Parse HTTP headers (php HTTP functions but generally, this packet isn't installed).
     *
     * @source http://www.php.net/manual/en/function.http-parse-headers.php#112917
     *
     * @param string $raw_headers Contain HTTP headers
     *
     * @return array<int|string, string|string[]>
     */
    public static function httpParseHeaders(string $raw_headers): array
    {
        $headers = [];
        $key = '';
        foreach (explode("\n", $raw_headers) as $h) {
            $h = explode(':', $h, 2);
            if (isset($h[1])) {
                if (! isset($headers[$h[0]])) {
                    $headers[$h[0]] = trim($h[1]);
                } elseif (\is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], [trim($h[1])]);
                } else {
                    $headers[$h[0]] = [...[$headers[$h[0]]], ...[trim($h[1])]];
                }

                $key = $h[0];
            } elseif (str_starts_with($h[0], "\t")) {
                $headers[$key] .= "\r\n\t".trim($h[0]);
            } elseif (! $key) {
                $headers[0] = trim($h[0]);
            }
        }

        return $headers;
    }

    /**
     * This is taken from the GuzzleHTTP/PSR7 library,
     * see https://github.com/guzzle/psr7 for more info.
     *
     * Parse an array of header values containing ";" separated data into an
     * array of associative arrays representing the header key value pair
     * data of the header. When a parameter does not contain a value, but just
     * contains a key, this function will inject a key with a '' string value.
     *
     * @param string|string[] $header header to parse into components
     *
     * @return array<int, array<int|string, string>> returns the parsed header values
     *
     * @psalm-suppress RedundantCast
     */
    public static function parseHeader(array|string $header): array
    {
        static $trimmed = "\"'  \n\t\r";
        $params = [];
        $matches = [];
        foreach (self::normalizeHeader($header) as $val) {
            $part = [];
            foreach (\Safe\preg_split('/;(?=([^"]*"[^"]*")*[^"]*$)/', $val) as $kvp) {
                if (preg_match_all('#<[^>]+>|[^=]+#', (string) $kvp, $matches)) {
                    $m = $matches[0];
                    if (isset($m[1])) {
                        $part[trim((string) $m[0], $trimmed)] = trim((string) $m[1], $trimmed);
                    } else {
                        $part[] = trim((string) $m[0], $trimmed);
                    }
                }
            }

            if ([] !== $part) {
                $params[] = $part;
            }
        }

        return $params;
    }

    /**
     * This is taken from the GuzzleHTTP/PSR7 library,
     * see https://github.com/guzzle/psr7 for more info.
     *
     * Converts an array of header values that may contain comma separated
     * headers into an array of headers with no comma separated values.
     *
     * @param string|string[] $header header to normalize
     *
     * @return string[] returns the normalized header field values
     */
    protected static function normalizeHeader(array|string $header): array
    {
        if (! \is_array($header)) {
            return array_map('trim', explode(',', $header));
        }

        $result = [];
        foreach ($header as $value) {
            foreach ((array) $value as $v) {
                if (! str_contains($v, ',')) {
                    $result[] = $v;

                    continue;
                }

                foreach (\Safe\preg_split('/,(?=([^"]*"[^"]*")*[^"]*$)/', $v) as $vv) {
                    $result[] = trim((string) $vv);
                }
            }
        }

        return $result;
    }

    public static function checkContentType(string $line, string $expected = 'text/html'): bool
    {
        return str_starts_with(strtolower(trim($line)), 'content-type') && str_contains($line, $expected);
    }

    public static function checkStatusCode(string $line, int $expected = 200): bool
    {
        return str_starts_with(strtolower(trim($line)), 'http') && str_contains($line, ' '.$expected.' ');
    }
}
