<?php

namespace PiedWeb\SeoStatus\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private SearchRepository $searchRepo;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->searchRepo = $this->entityManager->getRepository(Search::class);
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
            new TwigFunction('generate_filters_uri_parameter', [$this, 'generateFilterUriParameter'], $options),
            new TwigFunction('random_string', [$this, 'generateRandomString'], $options),
            new TwigFunction('serp_feature', [$this, 'getSerpFeature'], $options),
            new TwigFunction('get_search', [$this, 'getSearch'], $options),
        ];
    }

    public function dateFromInt(int|string $datetime, string $format = 'd/m/y'): string
    {
        if (0 === $datetime) {
            return '-';
        }

        return \Safe\DateTime::createFromFormat('ymdHi', (string) $datetime)->format($format);
    }

    /**
     * @param array<mixed> $filters
     */
    public function generateFilterUriParameter(array $filters): string
    {
        return base64_encode(urlencode(\Safe\json_encode($filters)));
    }

    public function generateRandomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = \strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * @return array<mixed>
     */
    public function getSerpFeature(string $serpFeature = ''): array
    {
        $list = [
            'ImagePack' => ['label' => 'Images', 'icon' => 'images'],
            'Video' => ['label' => 'Video', 'icon' => 'video'],
            'Ads' => ['label' => 'Ads', 'icon' => 'dollar-sign'],
            'Local Pack' => ['label' => 'Local', 'icon' => 'location-dot'],
            'PositionZero' => ['label' => 'PositionZero', 'icon' => '0'],
            'KnowledgePanel' => ['label' => 'KnowledgePanel', 'icon' => 'snowplow'],
            'PeolpleAlsoAsked' => ['label' => 'PeolpleAlsoAsked', 'icon' => 'question'],
            'News' => ['label' => 'News', 'icon' => 'newspaper'],
            'Reviews' => ['label' => 'Reviews', 'icon' => 'star-half-stroke'],
        ];

        if ('' === $serpFeature) {
            return $list;
        }

        return $list[$serpFeature] ?? throw new Exception($serpFeature);
    }

    public function getSearch(string $keyword): Search
    {
        return $this->searchRepo->findOrCreate($keyword);
    }
}
