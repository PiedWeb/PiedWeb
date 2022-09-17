<?php

namespace PiedWeb\SeoStatus\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;

/**
 * @extends ServiceEntityRepository<SearchResults>
 *
 * @method SearchResults|null  find($id, $lockMode = null, $lockVersion = null)
 * @method SearchResults|null  findOneBy(array $criteria, array $orderBy = null)
 * @method list<SearchResults> findAll()
 * @method list<SearchResults> findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchResultsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchResults::class);
    }
}
