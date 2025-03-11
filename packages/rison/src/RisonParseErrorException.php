<?php

namespace PiedWeb\Rison;

class RisonParseErrorException extends \RuntimeException
{
    public function __construct(protected string $rison, string $message, int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getRison(): string
    {
        return $this->rison;
    }
}
