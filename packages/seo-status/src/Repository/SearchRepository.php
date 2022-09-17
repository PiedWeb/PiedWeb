<?php

namespace PiedWeb\SeoStatus\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Search\Search;

/**
 * @extends ServiceEntityRepository<Search>
 *
 * @method Search|null find($id, $lockMode = null, $lockVersion = null)
 * @method Search|null findOneBy(array $criteria, array $orderBy = null)
 * @method Search[]    findAll()
 * @method Search[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    private function getQueryToFindSearchToExtract(int $maxResults = 10): Query
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->where('sgd.nextExtractionFrom <= :now')
            ->setParameter('now', (int) (new \DateTime('now'))->format('ymdHi'))
            ->orderBy('sgd.nextExtractionFrom', 'ASC')
            ->setMaxResults($maxResults)
            ->getQuery();
    }

    public function findOneSearchToExtract(): ?Search
    {
        return $this->getQueryToFindSearchToExtract(1) // @phpstan-ignore-line
            ->getOneOrNullResult();
    }
}
