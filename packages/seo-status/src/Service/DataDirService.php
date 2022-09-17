<?php

namespace PiedWeb\SeoStatus\Service;

final class DataDirService implements \Stringable
{
    public function __construct(
        private string $kernelEnvironment,
        private string $appDataDir,
    ) {
    }

    public function __toString(): string
    {
        return $this->get();
    }

    public function get(): string
    {
        $env = 'test' === $this->kernelEnvironment ? 'test' : 'prod';

        return str_replace('/env/',  '/'.$env.'/', $this->appDataDir);
    }
}
