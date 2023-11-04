<?php

namespace PiedWeb\Extractor;

use PiedWeb\TextAnalyzer\CleanText;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Component\Serializer\Annotation\Ignore;

final class Link
{
    /** @var int wrapper */
    public const LINK_A = 1;

    /** @var int wrapper */
    public const LINK_SRC = 4;

    /** @var int wrapper */
    public const LINK_3XX = 2;

    /** @var int not used ?!! */
    public const LINK_301 = 3;

    // ---
    /** @var int type */
    public const LINK_SELF = 1;

    /** @var int type */
    public const LINK_INTERNAL = 2;

    /** @var int type not used */
    public const LINK_SUB = 3;

    /** @var int type */
    public const LINK_EXTERNAL = 4;

    private ?string $url = null;

    private string $to;

    private ?string $parentUrl = null;

    private bool $mayFollow;

    private ?string $anchor = null;

    private bool $internal;

    private int $wrapper;

    private int $type;

    #[Ignore]
    private ?Url $urlStd = null;

    #[Ignore]
    private ?Url $parentUrlStd = null;

    private ?\DOMElement $element = null;

    /**
     * Always submit absoute Url !
     */
    public static function initialize(
        string $url,
        Url $parentUrl,
        bool $parentMayFollow = true,
        \DOMElement $element = null
    ): self {
        $self = new self();
        $self->element = $element;
        $self->mayFollow = $self->retrieveMayFollow($parentMayFollow);
        $self->url = UrlNormalizer::normalizeUrl($url);
        $self->urlStd = (new Url($self->url));
        $self->parentUrl = $parentUrl->get();
        $self->parentUrlStd = $parentUrl;
        $self->internal = $self->urlStd->getHost() === $parentUrl->getHost();
        $self->to = $self->internal ? $self->urlStd->getAbsoluteUri(true, true) : $self->url;
        $self->wrapper = null !== $self->element ? $self->getWrapperFrom($self->element) : 0;
        $self->type = $self->retrieveType();

        return $self;
    }

    public static function createRedirection(string $url, Url $parentUrl): self
    {
        $self = self::initialize($url, $parentUrl);
        $self->wrapper = self::LINK_3XX;

        return $self;
    }

    public function toMarkdown(): string
    {
        return '['.$this->anchor.']('.$this->url.')';
    }

    private function getWrapperFrom(\DOMElement $element): int
    {
        if ('a' == $element->tagName && $element->getAttribute('href')) {
            return self::LINK_A;
        }

        if ('' !== $element->getAttribute('src')) {
            return self::LINK_SRC;
        }

        return 0;
    }

    public function getAnchor(): string
    {
        if (null !== $this->anchor) {
            return $this->anchor;
        }

        if (null === $this->element) {
            return '';
        }

        // Get classic text anchor
        $anchor = $this->element->textContent;

        // If get nothing, then maybe we can get an alternative text (eg: img)
        if ('' === $anchor) {
            $alt = (new DomCrawler($this->element))->filter('*[alt]');
            if ($alt->count() > 0) {
                $anchor = $alt->eq(0)->attr('alt') ?? '';
            }
        }

        // Limit to 50 chars -> Totally subjective
        return $this->anchor = mb_substr(CleanText::fixEncoding($anchor), 0, 49);
    }

    private function retrieveMayFollow(bool $parentMayFollow): bool
    {
        // check meta robots and headers
        if (! $parentMayFollow) {
            return false;
        }

        // check "type" rel
        if (null === $this->element) {
            return true;
        }

        if ('' === $this->element->getAttribute('rel')) {
            return true;
        }

        if ('0' === $this->element->getAttribute('rel')) {
            return true;
        }

        return ! preg_match('(nofollow|sponsored|ugc)', $this->element->getAttribute('rel'));
    }

    #[Ignore]
    public function isSubLink(): bool
    {
        if ($this->internal) {
            return false;
        }

        return $this->getUrlStd()->getRegistrableDomain() === $this->getParentUrlStd()->getRegistrableDomain();
    }

    #[Ignore]
    public function isSelfLink(): bool
    {
        if (! $this->internal) {
            return false;
        }

        return $this->getUrlStd()->getDocumentUrl() == $this->getParentUrlStd()->getDocumentUrl();
    }

    private function retrieveType(): int
    {
        if ($this->isSelfLink()) {
            return self::LINK_SELF;
        }

        if ($this->internal) {
            return self::LINK_INTERNAL;
        }

        return self::LINK_EXTERNAL;
    }

    #[Ignore]
    public function getUrl(): string
    {
        return $this->url ?? throw new \Exception();
    }

    public function getTo(): string
    {
        return $this->to;
    }

    #[Ignore]
    public function getParentUrl(): string
    {
        return $this->parentUrl ?? throw new \Exception();
    }

    #[Ignore]
    public function getUrlStd(): Url
    {
        if (null === $this->urlStd) {
            $this->urlStd = (new Url($this->getUrl()));
        }

        return $this->urlStd;
    }

    #[Ignore]
    public function getParentUrlStd(): Url
    {
        if (null === $this->parentUrlStd) {
            $this->parentUrlStd = (new Url($this->getParentUrl()));
        }

        return $this->parentUrlStd;
    }

    public function getWrapper(): int
    {
        return $this->wrapper;
    }

    public function mayFollow(): bool
    {
        return $this->mayFollow;
    }

    public function getMayFollow(): bool
    {
        return $this->mayFollow;
    }

    public function getInternal(): bool
    {
        return $this->internal;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    #[Ignore]
    public function getElement(): \DOMElement
    {
        return $this->element ?? throw new \Exception();
    }

    public function setParentUrl(string $parentUrl): void
    {
        $this->parentUrl = $parentUrl;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setMayFollow(bool $mayFollow): void
    {
        $this->mayFollow = $mayFollow;
    }

    public function setAnchor(string $anchor): void
    {
        $this->anchor = $anchor;
    }

    public function setWrapper(int $wrapper): void
    {
        $this->wrapper = $wrapper;
    }

    public function setInternal(bool $internal): void
    {
        $this->internal = $internal;
    }
}
