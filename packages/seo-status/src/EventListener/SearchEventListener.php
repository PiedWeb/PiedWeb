<?php

namespace PiedWeb\SeoStatus\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Service\SearchExtractorService;
use PiedWeb\SeoStatus\Service\SearchResultsComparator;

#[AsEntityListener(event: Events::postUpdate, entity: Search::class, method: 'postUpdate')]
#[AsEntityListener(event: Events::preUpdate, entity: Search::class, method: 'preUpdate')]
#[AsEntityListener(event: Events::postPersist, entity: Search::class, method: 'postUpdate')]
#[AsEntityListener(event: Events::preRemove, entity: Search::class, method: 'preRemove')]
class SearchEventListener
{
    public function __construct(
        private SearchExtractorService $exporter,
        private SearchResultsComparator $comparator,
    ) {
    }

    public function postUpdate(Search $search): void
    {
        if (true === $search->disableExport) {
            $this->exporter->exportSearchToJson($search);
        }
    }

    public function preUpdate(Search $search): void
    {
        if (true === $search->getSearchGoogleData()->firstExtractionWasRunned) {
            $this->comparator->updateSimilar($search);
        }
    }

    public function preRemove(Search $search): void
    {
        $this->exporter->deleteSearch($search);
    }
}
