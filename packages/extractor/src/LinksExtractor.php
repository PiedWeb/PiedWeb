<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

class LinksExtractor
{
    /**
     * @var string
     */
    final public const SELECT_A = 'a[href]';

    /**
     * @var string
     */
    final public const SELECT_ALL = '[href],[src]';

    /** @var Link[] */
    private readonly array $links;

    /** @var array<int, array<Link>> */
    private array $linksPerType = [];

    public function __construct(
        private readonly Url $requestedUrl,
        private readonly Crawler $crawler,
        private readonly string $headers,
        private readonly string $selector = self::SELECT_A
    ) {
        $this->links = $this->extract();
        $this->classifyLinks();
    }

    /**
     * @return Link[]
     */
    public function get(?int $type = null): array
    {
        if (null !== $type) {
            return $this->linksPerType[$type] ?? [];
        }

        return $this->links;
    }

    /**
     * @return array<int, array<Link>>
     */
    public function getLinksPerType(): array
    {
        return $this->linksPerType;
    }

    /**
     * Return duplicate links
     * /test and /test#2 are not duplicates.
     */
    public function getNbrDuplicateLinks(): int
    {
        $links = $this->get();
        $u = [];
        foreach ($links as $link) {
            $u[$link->getUrl()] = 1;
        }

        return \count($links) - \count($u);
    }

    private function classifyLinks(): void
    {
        foreach ($this->links as $link) {
            $this->linksPerType[$link->getType()][] = $link;
        }
    }

    /**
     * @return Link[]
     */
    private function extract(): array
    {
        $links = [];
        $elements = $this->crawler->filter($this->selector);
        $parentMayFollow = (new FollowExtractor($this->crawler, $this->headers))->mayFollow();
        $parentBase = (new BaseExtractor($this->crawler))->get() ?? $this->requestedUrl;

        foreach ($elements as $element) {
            if (! $element instanceof \DOMElement) {
                throw new \LogicException('check your selector');
            }

            $url = $this->extractUrl($element);
            if (null !== $url) {
                $url = $parentBase->resolve($url);
                $links[] = new Link($url, $this->requestedUrl, $parentMayFollow, $element);
            }
        }

        return $links;
    }

    /**
     * @return string|null absolute url
     */
    private function extractUrl(\DOMElement $element): ?string
    {
        $attributes = explode(',', str_replace(['a[', '*[', '[', ']'], '', $this->selector));
        foreach ($attributes as $attribute) {
            $url = $element->getAttribute($attribute);
            if ('' !== $url) {
                break;
            }
        }

        if ('' === $url) {
            return null;
        }

        if ('0' === $url) {
            return null;
        }

        if (! Helper::isWebLink($url)) {
            return null;
        }

        if (str_starts_with($url, '////')) {
            return null;
        }

        return $this->requestedUrl->resolve($url);
    }
}
