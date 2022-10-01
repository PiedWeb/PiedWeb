<?php

namespace PiedWeb\SeoStatus\Service;

use PiedWeb\SeoStatus\Entity\Search\Search;

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

    public function getSearchDir(Search $search): string
    {
        return $this->get().$search->getCode().'/'.$search->getHashId().'/';
    }
}
