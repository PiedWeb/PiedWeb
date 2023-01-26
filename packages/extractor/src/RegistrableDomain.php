<?php

/**
 * Entity.
 */

namespace PiedWeb\Extractor;

use Pdp\Rules;

final class RegistrableDomain
{
    private static ?Rules $rules = null;

    public static function get(string $host): string
    {
        return self::getRules()->resolve($host)->registrableDomain()->toString();
    }

    private static function getRules(): Rules
    {
        // todo automatic update from https://publicsuffix.org/list/public_suffix_list.dat
        return self::$rules ??= Rules::fromPath(__DIR__.'/public_suffix_list.dat');
    }
}
