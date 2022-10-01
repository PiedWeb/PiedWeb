<?php

namespace PiedWeb\SeoStatus\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct()
    {
    }

    /**
     * @return \Twig\TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('app_date', [$this, 'dateFromInt']),
        ];
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        $options = ['is_safe' => ['html'], 'needs_environment' => false];

        return [
            // new TwigFunction('search_retrieve_pos', [$this, 'retrieveSearchResultFor'], $options),
        ];
    }

    public function dateFromInt(int|string $datetime, string $format = 'd/m/y'): string
    {
        if (0 === $datetime) {
            return '-';
        }

        return \Safe\DateTime::createFromFormat('ymdHi', (string) $datetime)->format($format);
    }
}
