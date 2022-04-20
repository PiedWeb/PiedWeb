<?php

namespace PiedWeb\Extractor;

use DOMElement;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

final class Link
{
    private Url $url;

    private ?string $anchor = null;

    // wrapper related
    /**
     * @var int
     */
    public const LINK_A = 1;

    /**
     * @var int
     */
    public const LINK_SRC = 4;

    /**
     * @var int
     */
    public const LINK_3XX = 2;

    /**
     * @var int
     */
    public const LINK_301 = 3;

    // type related
    /**
     * @var int
     */
    public const LINK_SELF = 1;

    /**
     * @var int
     */
    public const LINK_INTERNAL = 2;

    /**
     * @var int
     */
    public const LINK_SUB = 3;

    /**
     * @var int
     */
    public const LINK_EXTERNAL = 4;

    public function __toString(): string
    {
        return '['.$this->anchor.']('.$this->url->get().')';
    }

    /**
     * Add trailing slash for domain. Eg: https://piedweb.com => https://piedweb.com/ and '/test ' = '/test'.
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ('' == preg_replace('@(.*\://?([^\/]+))@', '', $url)) {
            $url .= '/';
        }

        return $url;
    }

    private function setWrapper(DOMElement $element): void
    {
        if ('a' == $element->tagName && $element->getAttribute('href')) {
            $this->wrapper = self::LINK_A;

            return;
        }

        if ('' !== $element->getAttribute('src')) {
            $this->wrapper = self::LINK_SRC;

            return;
        }
    }

    /**
     * Always submit absoute Url !
     */
    public function __construct(
        string $url,
        private Url $parentUrl,
        private bool $parentMayFollow = true,
        private ?DOMElement $element = null,
        private ?int $wrapper = null
    ) {
        $this->url = new Url(self::normalizeUrl($url));
        $this->setAnchor();
        if (null !== $this->element) {
            $this->setWrapper($this->element);
        }
    }

    public static function createRedirection(string $url, Url $parentUrl, int $redirType = self::LINK_3XX): self
    {
        return new self($url, $parentUrl, true,  null, $redirType);
    }

    public function getWrapper(): ?int
    {
        return $this->wrapper;
    }

    private function setAnchor(): void
    {
        if (null === $this->element) {
            return;
        }

        // Get classic text anchor
        $this->anchor = $this->element->textContent;

        // If get nothing, then maybe we can get an alternative text (eg: img)
        if ('' === $this->anchor) {
            $alt = (new DomCrawler($this->element))->filter('*[alt]');
            if ($alt->count() > 0) {
                $this->anchor = $alt->eq(0)->attr('alt') ?? '';
            }
        }

        // Limit to 100 characters
        // Totally subjective
        $this->anchor = substr(Helper::clean($this->anchor), 0, 99);
    }

    public function getUrl(): Url
    {
        return $this->url;
    }

    public function getPageUrl(): \League\Uri\Http
    {
        return $this->url->getDocumentUrl();
    }

    public function getParentUrl(): Url
    {
        return $this->parentUrl;
    }

    public function getAnchor(): ?string
    {
        return $this->anchor;
    }

    public function getElement(): ?DOMElement
    {
        return $this->element;
    }

    public function mayFollow(): bool
    {
        // check meta robots and headers
        if (! $this->parentMayFollow) {
            return false;
        }

        // check "type" rel
        if (null !== $this->element && $this->element->getAttribute('rel')) {
            if (preg_match('(nofollow|sponsored|ugc)', $this->element->getAttribute('rel'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string
     */
    public function getRelAttribute(): ?string
    {
        return null !== $this->element ? $this->element->getAttribute('rel') : null;
    }

    public function isInternalLink(): bool
    {
        return $this->url->getOrigin() == $this->getParentUrl()->getOrigin();
    }

    public function isSubLink(): bool
    {
        return ! $this->isInternalLink()
            && $this->url->getRegistrableDomain() == $this->parentUrl->getRegistrableDomain();
        // && strtolower(substr($this->getHost(), -strlen($this->parentDomain))) === $this->parentDomain;
    }

    public function isSelfLink(): bool
    {
        return $this->isInternalLink()
            && $this->url->getDocumentUrl() == $this->parentUrl->getDocumentUrl();
    }

    public function getType(): int
    {
        if ($this->isSelfLink()) {
            return self::LINK_SELF;
        }

        if ($this->isInternalLink()) {
            return self::LINK_INTERNAL;
        }

        if ($this->isSubLink()) {
            return self::LINK_SUB;
        }

        return self::LINK_EXTERNAL;
    }
}
