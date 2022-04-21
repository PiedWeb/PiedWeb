<?php

/**
 * Wrapper for League\Uri.
 *
 * Permits to cache registrableDomain and Origin
 */

namespace PiedWeb\Extractor;

use League\Uri\Http;
use League\Uri\UriInfo;
use League\Uri\UriResolver;

final class Url
{
    private Http $http;

    private string $origin = '';

    private ?string $registrableDomain = null;

    public function __construct(string $url)
    {
        $this->http = Http::createFromString($url);

        if (! UriInfo::isAbsolute($this->http)) {
            throw new \Exception('$url must be absolute (`'.$url.'`)');
        }
    }

    public function resolve(string $url): string
    {
        $resolved = UriResolver::resolve(Http::createFromString($url), $this->http);

        return $resolved->__toString();
    }

    public function getHttp(): Http
    {
        return $this->http;
    }

    public function getScheme(): string
    {
        return $this->http->getScheme();
    }

    public function getHost(): string
    {
        return $this->http->getHost();
    }

    public function getOrigin(): string
    {
        return '' !== $this->origin ? $this->origin : ($this->origin = UriInfo::getOrigin($this->http) ?? '');
    }

    public function getRegistrableDomain(): string
    {
        return $this->registrableDomain ?? ($this->registrableDomain = RegistrableDomain::get($this->http->getHost()));
    }

    public function getDocumentUrl(): Http
    {
        return $this->http->withFragment('');
    }

    public function getAbsoluteUri(): string
    {
        // return substr($this->get(), \strlen($this->getOrigin()));
        return substr($this->http->withFragment(''), \strlen($this->getOrigin()));
    }

    public function get(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        return (string) $this->http;
    }
}
