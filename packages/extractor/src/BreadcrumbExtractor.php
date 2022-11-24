<?php

/**
 * Entity.
 */

namespace PiedWeb\Extractor;

/**
 * Quelques notes :
 * - Un bc ne contient pas l'élément courant.
 */
final class BreadcrumbExtractor
{
    /**
     * @var BreadcrumbItem[]
     */
    private array $breadcrumb = [];

    /**
     * @var string
     */
    public const BC_RGX = '#<(div|p|nav|ul)[^>]*(id|class)="?(breadcrumbs?|fil_?d?arian?ne)"?[^>]*>(.*)<\/(\1)>#siU';

    /**
     * @var string[]
     */
    public const BC_DIVIDER = [
        'class="?navigation-pipe"?',
        '&gt;',
        'class="?divider"?',
        '›',
        '</li>',
    ];

    public function __construct(
        private readonly string $html,
        private readonly Url $parentUrl
    ) {
    }

    /**
     * @return BreadcrumbItem[]
     */
    public function extractBreadcrumb(): array
    {
        $breadcrumb = $this->findBreadcrumb();
        if (null === $breadcrumb) {
            return [];
        }

        foreach (self::BC_DIVIDER as $divider) {
            $exploded = $this->divideBreadcrumb($breadcrumb, $divider);
            if (null !== $exploded) {
                $this->extractBreadcrumbData($exploded);

                break;
            }
        }

        return $this->breadcrumb;
    }

    private function findBreadcrumb(): ?string
    {
        preg_match(self::BC_RGX, $this->html, $match);

        return $match[4] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function divideBreadcrumb(string $breadcrumb, string $divider): ?array
    {
        $exploded = preg_split('/'.str_replace('/', '\/', $divider).'/si', $breadcrumb);

        return false !== $exploded && \count($exploded) > 1 ? $exploded : null;
    }

    /**
     * On essaye d'extraire l'url et l'ancre.
     *
     * @param array<int, string> $array
     */
    private function extractBreadcrumbData(array $array): void
    {
        foreach ($array as $a) {
            $link = $this->extractHref($a);
            if (null === $link || $link == $this->parentUrl->get()) {
                break;
            }

            $this->breadcrumb[] = new BreadcrumbItem(
                $link,
                $this->extractAnchor($a)
            );
        }
    }

    private function extractAnchor(string $str): string
    {
        return trim(strtolower(Helper::htmlToPlainText($str)), '> ');
    }

    private function extractHref(string $str): ?string
    {
        $regex = ['href="([^"]*)"', 'href=\'([^\']*)\'', 'href=(\S+) '];
        foreach ($regex as $r) {
            if (preg_match('/'.$r.'/siU', $str, $match) && Helper::isWebLink($match[1])) {
                return $this->parentUrl->resolve($match[1]);
            }
        }

        return null;
    }
}
