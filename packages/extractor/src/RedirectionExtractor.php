<?php

namespace PiedWeb\Extractor;

final class RedirectionExtractor
{
    /**
     * @param array<mixed> $headers
     */
    public function __construct(
        private Url $url,
        private array $headers
    ) {
    }

    /**
     * @return ?string absolute url
     */
    public function getRedirection(): ?string
    {
        $headers = array_change_key_case([] !== $this->headers ? $this->headers : []);
        if (isset($headers['location']) && \is_string($headers['location']) && Helper::isWebLink($headers['location'])) {
            return $this->url->resolve($headers['location']);
        }

        return null;
    }

    public function getRedirectionLink(): ?Link
    {
        $redirection = $this->getRedirection();

        if (null !== $redirection) {
            return Link::createRedirection($redirection, $this->url);
        }

        return null;
    }

    public function isRedirectToHttps(): bool
    {
        $redirUrl = $this->getRedirection();

        return null !== $redirUrl && preg_replace('#^http:#', 'https:', $this->url->get(), 1) == $redirUrl;
    }
}
