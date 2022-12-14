<?php

namespace PiedWeb\Extractor;

final class RedirectionExtractor
{
    /**
     * @param array<int|string, string|string[]> $headers
     */
    public function __construct(
        private readonly Url $url,
        private readonly array $headers
    ) {
    }

    /**
     * @return ?string absolute url
     */
    public function getRedirection(): ?string
    {
        $headers = array_change_key_case($this->headers);
        if (! isset($headers['location'])) {
            return null;
        }

        if (! \is_string($headers['location'])) {
            return null;
        }

        if (! Helper::isWebLink($headers['location'])) {
            return null;
        }

        return $this->url->resolve($headers['location']);
    }

    public function getRedirectionLink(): ?Link
    {
        $redirection = $this->getRedirection();

        return null !== $redirection ? Link::createRedirection($redirection, $this->url)
                : null;
    }

    public function isRedirectToHttps(): bool
    {
        $redirUrl = $this->getRedirection();

        return null !== $redirUrl && preg_replace('#^http:#', 'https:', $this->url->get(), 1) == $redirUrl;
    }
}
