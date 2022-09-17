<?php

namespace PiedWeb\Curl;

/**
 * This class is an example.
 */
class MultipleCheckInHeaders
{
    protected ?int $code = null;

    public function __construct(protected int $expectedCode = 200, protected string $expectedType = 'text/html')
    {
    }

    public function check(string $line): bool
    {
        if (null === $this->code && Helper::checkStatusCode($line, $this->expectedCode)) {
            $this->code = 200;
        }

        return 200 == $this->code && Helper::checkContentType($line, $this->expectedType);
    }
}
