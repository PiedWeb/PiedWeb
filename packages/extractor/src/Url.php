<?php

/**
 * Wrapper for League\Uri.
 *
 * Permits to cache registrableDomain and Origin
 */

namespace PiedWeb\Extractor;

use League\Uri\BaseUri;
use League\Uri\Http;
use League\Uri\UriInfo;

final class Url implements \Stringable
{
    private readonly Http $http;

    private string $origin = '';

    private ?string $registrableDomain = null;

    public function __construct(string $url)
    {
        $this->http = Http::new($url);

        if (! BaseUri::from($this->http)->isAbsolute()) {
            throw new \Exception('$url must be absolute (`'.$url.'`)');
        }
    }

    public function resolve(string $url): string
    {
        $resolved = BaseUri::from($this->http)->resolve(Http::new(trim($url)))->getUri();

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
        // return '' !== $this->origin ? $this->origin : ($this->origin = UriInfo::getOrigin($this->http) ?? '');
        return '' !== $this->origin ? $this->origin : ($this->origin = BaseUri::from($this->http)->origin()?->__toString() ?? '');
    }

    public function getRegistrableDomain(): string
    {
        return $this->registrableDomain ?? ($this->registrableDomain = RegistrableDomain::get($this->http->getHost()));
    }

    public function getDocumentUrl(): Http
    {
        return $this->http->withFragment('');
    }

    public function getAbsoluteUri(bool $withFragment = false, bool $ltrimSlash = false): string
    {
        $absolute = substr(
            (string) ($withFragment ? $this->http : $this->http->withFragment('')),
            \strlen($this->getOrigin())
        );
        if ($ltrimSlash) {
            $absolute = ltrim($absolute, '/');
        }

        return '' === $absolute ? '/' : $absolute;
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
