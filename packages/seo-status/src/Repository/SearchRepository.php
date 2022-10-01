<?php

namespace PiedWeb\SeoStatus\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    /** @var Search[] */
    private array $index = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    public function findOrCreate(string $keyword, bool &$creation = false): Search
    {
        $keyword = Search::normalizeKeyword($keyword);

        if (isset($this->index[$keyword])) {
            return $this->index[$keyword];
        }

        $search = $this->findOneBy(['keyword' => $keyword]);
        if (null === $search) {
            $creation = true;
            $search = (new Search())->setKeyword($keyword);
            $this->getEntityManager()->persist($search);
        }

        return $this->index[$keyword] = $search;
    }

    private function getQueryToFindSearchToExtract(int $maxResults = 10): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->where('sgd.nextExtractionFrom <= :now')
            ->setParameter('now', (int) (new \DateTime('now'))->format('ymdHi'))
            ->andWhere('(sgd.lastExtractionAskedAt = 0 OR sgd.lastExtractionAskedAt < '.(int) (new \DateTime('now'))->modify('-3 days')->format('ymdHi').')')
            ->orderBy('sgd.nextExtractionFrom', 'ASC')
            ->setMaxResults($maxResults);
    }

    public function findOneSearchToExtract(): ?Search
    {
        /** @var ?Search */
        $search = $this->getQueryToFindSearchToExtract(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $search) {
            $search->getSearchGoogleData()->setLastExtractionAskedAt((int) (new DateTime())->format('ymdHi'));
            $this->getEntityManager()->flush();
        }

        return $search;
    }

    /**
     * @return int[] where int is search's id
     */
    public function findSearchToExtract(int $max = 10080): array
    {
        return $this->getQueryToFindSearchToExtract($max) // @phpstan-ignore-line
            ->select('id')
            ->getQuery()
            ->getScalarResult();
    }
}
