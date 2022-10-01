<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchResult;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;
use PiedWeb\SeoStatus\Entity\Url\Url;
use Psr\Log\LoggerInterface;
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
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function deserializeSearchResults(Search $search, string $json, int|string $extractionAskedAt): SearchResults
    {
        if ((int) substr((string) $search->getSearchGoogleData()->getLastExtractionAskedAt(), 0, 6) <= (int) substr((string) $extractionAskedAt, 0, 6)) {
            $search->getSearchGoogleData()->setLastExtractionAskedAt(0);
        }

        $serializer = new Serializer([new ObjectNormalizer()],  [new JsonEncoder()]);
        $searchResults = new SearchResults();
        $searchResults->setExtractedAt($searchResults->getExtractedAt());
        $searchResults->setSearchGoogleData($search->getSearchGoogleData());
        $searchResults->setPrevious($search->getSearchGoogleData()->getLastSearchResults());
        // ↥↥↥ Ceci implique que les résultats de recherches sont importées dans l'ordre chronologique...
        $serializer->deserialize($json, SearchResults::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $searchResults,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['results'],
        ]);
        $search->getSearchGoogleData()->updateExtractionMetadata($searchResults->getExtractedAt());
        $search->getSearchGoogleData()->setLastSearchResults($searchResults);

        /** @var array<array{'pos': int, 'pixelPos': int, 'url': string, 'ads': bool}> */
        $results = \Safe\json_decode($json, true)['results']; // @phpstan-ignore-line
        foreach ($results as $result) {
            if ('' === $result['url']) {
                if ($this->logger) {
                    $this->logger->info('unsuported result for '.$search->getKeyword());
                }

                continue;
            }

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

        $this->importRelated($searchResults);

        return $searchResults;
    }

    private function importRelated(SearchResults $searchResults): void
    {
        foreach ($searchResults->getRelatedSearches() as $search) {
            $this->entityManager->getRepository(Search::class)->findOrCreate($search);
        }
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
