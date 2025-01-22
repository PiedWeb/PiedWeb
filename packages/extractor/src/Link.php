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

    #[Ignore]
    public string $to;

    public readonly bool $mayFollow;

    /** empty if not found, limited to 50 chars */
    public readonly string $anchor;

    /** 1 = a[href], 2 = src, 3 = 301, 4 = redirection, see Link::LINK_* */
    public readonly int $wrapper;

    public readonly bool $internal;

    /** internal or external with a code, see Link::TYPE_* || prefer use `internal` property */
    public int $type;

    // public   ?string $parentUrl;
    /**
     * Always submit absoute Url !
     */
    public function __construct(
        public string $url,
        public Url|string $parentUrl,
        bool $parentMayFollow = true,
        #[Ignore]
        private readonly ?\DOMElement $element = null,
        public int $position = 0,
        ?int $wrapper = null,
    ) {
        if (\is_string($parentUrl)) {
            $parentUrl = new Url($url);
        }

        $this->mayFollow = $this->retrieveMayFollow($parentMayFollow);
        $this->url = UrlNormalizer::normalizeUrl($url);
        $this->parentUrl = $parentUrl->get();
        $this->internal = $this->getUrlStd()->getHost() === $parentUrl->getHost();
        $this->to = $this->internal ? $this->getUrlStd()->getAbsoluteUri(true, true) : $this->url;
        $this->wrapper = $wrapper ?? (null !== $this->element ? $this->getWrapperFrom($this->element) : 0);
        $this->type = $this->retrieveType();
        $this->anchor = $this->getAnchor();
    }

    public static function createRedirection(string $url, Url $parentUrl): self
    {
        return new self($url, $parentUrl, wrapper: self::LINK_3XX);
    }

    public function toMarkdown(): string
    {
        return '['.($this->anchor ?? '').']('.$this->url.')';
    }

    #[Ignore]
    public static function elementIsHyperlink(\DOMElement $element): bool
    {
        return 'a' === $element->tagName && $element->hasAttribute('href');
    }

    private function getWrapperFrom(\DOMElement $element): int
    {
        if (self::elementIsHyperlink($element)) {
            return self::LINK_A;
        }

        if ('' !== $element->getAttribute('src')) {
            return self::LINK_SRC;
        }

        return 0;
    }

    private function getAnchor(): string
    {
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
        return mb_substr(CleanText::fixEncoding($anchor), 0, 49);
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

    public function getTo(): string
    {
        return $this->to;
    }

    #[Ignore]
    private ?Url $urlStd = null;

    #[Ignore]
    private ?Url $parentUrlStd = null;

    #[Ignore]
    public function getUrlStd(): Url
    {
        if (null === $this->urlStd) {
            return $this->urlStd = new Url($this->url);
        }

        return $this->urlStd;
    }

    #[Ignore]
    public function getParentUrlStd(): Url
    {
        if (null === $this->parentUrlStd) {
            $this->parentUrlStd = (new Url($this->parentUrl ?? throw new \Exception()));
        }

        return $this->parentUrlStd;
    }
}
