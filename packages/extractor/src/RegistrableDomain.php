<?php

/**
 * Entity.
 */

namespace PiedWeb\Extractor;

use LogicException;
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
        if (null !== self::$rules) {
            return self::$rules;
        }

        $reflector = new \ReflectionClass("Pdp\Rules");
        $filename = $reflector->getFileName();
        if (false === $filename) {
            throw new LogicException();
        }

        $base = \dirname($filename, 2);

        return self::$rules = Rules::fromPath($base.'/test_data/public_suffix_list.dat');
    }
}
