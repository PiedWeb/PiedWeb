<?php

namespace PiedWeb\Extractor;

use Symfony\Component\DomCrawler\Crawler;

final class LinksExtractor
{
    /**
     * @var string
     */
    public const SELECT_A = 'a[href]';

    /**
     * @var string
     */
    public const SELECT_ALL = '[href],[src]';

    /** @var Link[] */
    private readonly array $links;

    /** @var array<int, array<Link>> */
    private array $linksPerType = [];

    /**
     * @param list<string> $attributes
     */
    public function __construct(
        private readonly Url $requestedUrl,
        private readonly Crawler $crawler,
        private readonly string $headers,
        private readonly string $selector = self::SELECT_A,
        private readonly array $attributes = ['href', 'src'],
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
            $u[$link->url] = 1;
        }

        return \count($links) - \count($u);
    }

    private function classifyLinks(): void
    {
        foreach ($this->links as $link) {
            $this->linksPerType[$link->type][] = $link;
        }
    }

    /**
     * @return Link[]
     */
    private function extract(): array
    {
        $links = [];
        $elements = str_starts_with($this->selector, '/')
            ? $this->crawler->filterXPath($this->selector)
            : $this->crawler->filter($this->selector);
        $parentMayFollow = (new FollowExtractor($this->crawler, $this->headers))->mayFollow();
        $parentBase = (new BaseExtractor($this->crawler))->get() ?? $this->requestedUrl;

        $position = 0;
        foreach ($elements as $element) {
            if (! $element instanceof \DOMElement) {
                throw new \LogicException('check your selector');
            }

            $isHyperlink = Link::elementIsHyperlink($element);
            if ($isHyperlink) {
                ++$position;
            }

            $url = $this->extractUrl($element);
            if (null === $url) {
                continue;
            }

            $url = $parentBase->resolve($url);
            $links[] = new Link(
                $url,
                $this->requestedUrl,
                $parentMayFollow,
                $element,
                position: $isHyperlink ? $position : 0
            );
        }

        return $links;
    }

    /**
     * @return string|null absolute url
     */
    private function extractUrl(\DOMElement $element): ?string
    {
        // $attributes = explode(',', str_replace(['a[', '*[', '[', ']'], '', $this->selector));
        foreach ($this->attributes as $attribute) {
            $url = $element->getAttribute($attribute);
            if ('' !== $url) {
                break;
            }
        }

        if (! isset($url) || '' === $url) {
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

        if ('#' === $url) {
            return null;
        }

        return $this->requestedUrl->resolve($url);
    }
}
