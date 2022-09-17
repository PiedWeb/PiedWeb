<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchResult;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;
use PiedWeb\SeoStatus\Entity\Url\Url;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SearchImportJsonService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function deserializeSearchResults(Search $search, string $json): SearchResults
    {
        $serializer = new Serializer([new ObjectNormalizer()],  [new JsonEncoder()]);
        $searchResults = new SearchResults();
        $searchResults->setSearchGoogleData($search->getSearchGoogleData());
        $searchResults->setPrevious($search->getSearchGoogleData()->getLastSearchResults());
        $search->getSearchGoogleData()->setLastSearchResults($searchResults);
        $serializer->deserialize($json, SearchResults::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $searchResults,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['results'],
        ]);

        /** @var array<array{'pos': int, 'pixelPos': int, 'url': string, 'ads': bool}> */
        $results = \Safe\json_decode($json, true)['results']; // @phpstan-ignore-line
        foreach ($results as $result) {
            $url = $this->entityManager->getRepository(Url::class)
                ->findOrCreate($result['url']);

            $searchResults->addResult(
                (new SearchResult())
                ->setPos($result['pos'])
                ->setPixelPos($result['pixelPos'])
                ->setUrl($url)
                ->setHost($url->getHost())
                ->setUri($url->getUri())
                ->setAds($result['ads'])
            );
        }

        return $searchResults;
    }

    public function deserializeSearch(string $json): Search
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());

        $serializer = new Serializer([new ArrayDenormalizer(), $objectNormalizer],  [new JsonEncoder()]);
        /** @var Search */
        $search = $serializer->deserialize($json, Search::class, 'json');
        $search->getSearchGoogleData()->setSearch($search);

        return $search;
    }
}
