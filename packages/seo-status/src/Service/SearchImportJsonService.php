<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchGoogleData;
use PiedWeb\SeoStatus\Entity\Search\SearchResult;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;
use PiedWeb\SeoStatus\Entity\Search\SearchVolumeData;
use PiedWeb\SeoStatus\Entity\Url\Url;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class SearchImportJsonService
{
    private SearchRepository $searchRepo;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private DataDirService $dataDir,
        private ?LoggerInterface $logger = null,
    ) {
        $this->searchRepo = $this->entityManager->getRepository(Search::class);
    }

    private function resetLastExtractionAskedAt(Search $search, int|string $extractionAskedAt): void
    {
        $lastExtractionAskedAtDay = (int) substr((string) $search->getSearchGoogleData()->getLastExtractionAskedAt(), 0, 6);
        if ($lastExtractionAskedAtDay <= (int) substr((string) $extractionAskedAt, 0, 6)) {
            $search->getSearchGoogleData()->setLastExtractionAskedAt(0);
        }
    }

    public function deserializeSearchResults(Search $search, string $json, int|string $extractionAskedAt): ?SearchResults
    {
        $this->resetLastExtractionAskedAt($search, $extractionAskedAt);

        $serializer = new Serializer([new ObjectNormalizer()],  [new JsonEncoder()]);
        $searchResults = new SearchResults();
        $searchResults->setExtractedAt($extractionAskedAt);
        $searchResults->setSearchGoogleData($search->getSearchGoogleData());
        $searchResults->setPrevious($search->getSearchGoogleData()->getLastSearchResults());
        // ↥↥↥ Ceci implique que les résultats de recherches sont importées dans l'ordre chronologique...
        $serializer->deserialize($json, SearchResults::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $searchResults,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['results', 'searchGoogleData', 'extractedAt'],
        ]);
        $searchResults->setSearchGoogleData($search->getSearchGoogleData()); // Just In Case
        $search->getSearchGoogleData()->updateExtractionMetadata($searchResults->getExtractedAt());
        $search->getSearchGoogleData()->setLastSearchResults($searchResults);

        $json = \Safe\json_decode($json, true);
        /** @var ?array<array{'pos': int, 'pixelPos': int, 'url': string, 'ads': bool}> */
        $results = $json['results'] ?? null; // @phpstan-ignore-line
        if (null === $results) {
            return null;
        }

        foreach ($results as $result) {
            if ('' === $result['url']) {
                if ($this->logger) {
                    // $this->logger->info('unsuported result for '.$search->getKeyword().'.'.$extractionAskedAt);
                    // .' '.\Safe\json_encode($result));
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
            )
                ->calculateMovement();
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

    public function deserializeExistingSearch(string $json, Search $search): Search
    {
        $stdSearch = \Safe\json_decode($json);

        if (! \is_object($stdSearch)) {
            throw new Exception();
        }

        if (isset($stdSearch->searchGoogleData) && isset($stdSearch->searchGoogleData->searchVolumeData)) {
            $jsonSearchVolumeData = \Safe\json_encode($stdSearch->searchGoogleData->searchVolumeData);
            $this->serializer->deserialize($jsonSearchVolumeData, SearchVolumeData::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $search->getSearchGoogleData()->getSearchVolumeData(),
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id', 'search', 'searchGoogleData', 'relatedTopicsLinks', 'mainRelatedTopic'],
            ]);
            $this->entityManager->persist($search->getSearchGoogleData()->getSearchVolumeData());
        }

        if (isset($stdSearch->searchGoogleData)) {
            $jsonSearchGoogleData = \Safe\json_encode($stdSearch->searchGoogleData);
            $this->serializer->deserialize($jsonSearchGoogleData, SearchGoogleData::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $search->getSearchGoogleData(),
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id', 'searchVolumeData', 'search'],
            ]);
            $this->entityManager->persist($search->getSearchGoogleData());
        }

        /** @var Search */
        $search = $this->serializer->deserialize($json, Search::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $search,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['id', 'searchGoogleData'],
        ]);

        return $search;
    }

    public function deserializeSearch(string $json, Search $search): Search
    {
        if (\Doctrine\ORM\UnitOfWork::STATE_MANAGED === $this->entityManager->getUnitOfWork()->getEntityState($search)) {
            return $this->deserializeExistingSearch($json, $search);
        }

        // $objectNormalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());

        // $serializer = new Serializer([new ArrayDenormalizer(), $objectNormalizer],  [new JsonEncoder()]);

        /** @var Search */
        $search = $this->serializer->deserialize($json, Search::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $search,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['id'],
        ]);
        $search->getSearchGoogleData()->setSearch($search);
        $search->disableExport = true;

        return $search;
    }

    public function importSearchResultsFile(Search $search, string $searchDataFilePath, string $fileContent): void
    {
        if (file_exists($searchDataFilePath)) {
            throw new Exception('Ever imported for another source...');
        }

        file_put_contents($searchDataFilePath, $fileContent);
    }

    public function importSearch(string|Search $keyword, bool $useIndex = false): void
    {
        $search = \is_string($keyword) ? $this->searchRepo->findOneByKeyword($keyword, $useIndex) : $keyword;
        $newSearch = $search instanceof \PiedWeb\SeoStatus\Entity\Search\Search ? false : true;
        $search = ($search ?? new Search())->setKeyword($keyword);

        $filepath = $this->dataDir->getSearchDir($search).'index.json';
        if (! file_exists($filepath)) {
            return;
        }

        $search = $this->deserializeSearch(
            \Safe\file_get_contents($filepath),
            $search
        );

        if ($newSearch) {
            $this->entityManager->persist($search);
        }

        $this->entityManager->flush();
        if ($useIndex) {
            $this->searchRepo->addToIndex($search);
        }
    }
}
