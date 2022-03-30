<?php

namespace PiedWeb\Google;

final class Logger
{
    public static bool $debug = true;

    public static function log(string $msg): void
    {
        if (self::$debug) {
            echo $msg.\chr(10);
        }
    }
}
