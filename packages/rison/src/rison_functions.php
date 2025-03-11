<?php

namespace PiedWeb\Rison;

function rison_decode(string $string): mixed
{
    try {
        $r = new RisonDecoder($string);

        return $r->decode();
    } catch (\InvalidArgumentException $e) {
        trigger_error($e->getMessage(), \E_USER_WARNING);

        return false;
    } catch (RisonParseErrorException $e) {
        trigger_error(\sprintf('%s (in "%s")', $e->getMessage(), $e->getRison()), \E_USER_WARNING);

        return false;
    }
}

function rison_encode(mixed $value): string|bool
{
    try {
        $r = new RisonEncoder($value);

        return $r->encode();
    } catch (\InvalidArgumentException $invalidArgumentException) {
        trigger_error($invalidArgumentException->getMessage(), \E_USER_WARNING);

        return false;
    }
}
