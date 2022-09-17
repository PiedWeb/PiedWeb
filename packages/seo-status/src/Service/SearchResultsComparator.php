<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchResult;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;

class SearchResultsComparator
{
    public const MaxResults = 20;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return int[]
     */
    private function getHashArrayFor(SearchResults $searchResults): array
    {
        $results = $searchResults->getResults()
            ->filter(fn (SearchResult $result) => true !== $result->isAds())
            ->slice(0, self::MaxResults);

        $return = array_map(fn (SearchResult $result) => $result->getUrl()->getId(), $results);

        return $return;
    }

    public function areComparable(Search $search1, Search $search2): bool
    {
        return $this->getSimilarityScore($search1, $search2) * 100 >= 70;
    }

    public function areSimilar(Search $search1, Search $search2): bool
    {
        return $this->getSimilarityScore($search1, $search2) * 100 >= 20;
    }

    public function getSimilarityScore(Search $search1, Search $search2): int|float
    {
        if (($sr1 = $search1->getSearchGoogleData()->getLastSearchResults()) === null
            || ($sr2 = $search2->getSearchGoogleData()->getLastSearchResults()) === null) {
            return 0;
        }

        $diff = array_diff($this->getHashArrayFor($sr1),  $this->getHashArrayFor($sr2));

        return (self::MaxResults - \count($diff)) / self::MaxResults;
    }

    public function updateSimilar(Search $currentSearch): void
    {
        $searches = $this->entityManager->getRepository(Search::class)->findAll();
        foreach ($searches as $search) {
            if ($search === $currentSearch) {
                continue;
            }

            if ($this->areSimilar($search, $currentSearch)) {
                $currentSearch->getSearchGoogleData()
                    ->addSimilar($search->getSearchGoogleData());

                continue;
            }

            if ($this->areComparable($search, $currentSearch)) {
                $currentSearch->getSearchGoogleData()
                    ->addComparable($search->getSearchGoogleData());

                continue;
            }
        }
    }
}
