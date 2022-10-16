<?php

namespace PiedWeb\SeoStatus\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchGoogleData;
use PiedWeb\SeoStatus\Entity\Search\SearchVolumeData;

/**
 * @extends ServiceEntityRepository<Search>
 *
 * @method Search|null find($id, $lockMode = null, $lockVersion = null)
 * @method Search|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Search[]    findAll()
 * @method Search[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchRepository extends ServiceEntityRepository
{
    /** @var array<string, ?Search> */
    private array $index = [];

    public function resetIndex(): self
    {
        $this->index = [];

        return $this;
    }

    public function loadIndex(): self
    {
        $searches = $this->findAll();
        foreach ($searches as $search) {
            $this->addToIndex($search);
        }

        return $this;
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    public function addToIndex(Search $search): self
    {
        $this->index[$search->getKeyword()] = $search;

        return $this;
    }

    public function findOneByKeyword(string $keyword, bool $onlyIndex = false): ?Search
    {
        return $this->index[$keyword] ??= $onlyIndex ? null : parent::findOneBy(['keyword' => $keyword]);
    }

    public function findOrCreate(string $keyword, bool &$creation = false): Search
    {
        $keyword = Search::normalizeKeyword($keyword);

        if (isset($this->index[$keyword]) && null !== $this->index[$keyword]) {
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

    public function getQueryToFindSearchToExtract(?int $maxResults = 10): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->where('sgd.nextExtractionFrom <= '.(int) (new \DateTime('now'))->format('ymdHi'))
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
            $this->updateLastExtractionAksedAt($search);
        }

        return $search;
    }

    public function updateLastExtractionAksedAt(Search $search, string $for = SearchGoogleData::class): void
    {
        $search->disableExport = true;
        $entity = SearchVolumeData::class === $for
            ? $search->getSearchVolumeData()
            : $search->getSearchGoogleData();

        $entity->setLastExtractionAskedAt((int) (new DateTime())->format('ymdHi'));

        if ($entity instanceof SearchVolumeData) {
            $entity->setSearchGoogleData($search->getSearchGoogleData());
        }

        $this->getEntityManager()->flush();
        $search->disableExport = false;
    }

    public function getQueryToFindSearchTrendsToExtract(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->innerJoin('sgd.searchVolumeData', 'svd')
            ->andWhere('(svd.lastExtractionAskedAt = 0 OR svd.lastExtractionAskedAt < '.(int) (new \DateTime('now'))->modify('-3 days')->format('ymdHi').')')
            ->orderBy('svd.lastExtractionAskedAt', 'ASC')
            ->orderBy('svd.lastExtractionAt', 'ASC')
            ->setMaxResults(1);
    }

    public function findOneSearchTrendsToExtract(): ?Search
    {
        /** @var ?Search */
        $search = $this->getQueryToFindSearchTrendsToExtract()
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $search) {
            $this->updateLastExtractionAksedAt($search, SearchVolumeData::class);
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

    public function countSearchToExtract(): int
    {
        return \intval($this->getQueryToFindSearchToExtract(null)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function countSearch(): int
    {
        return \intval($this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function countSearchTrendsExtracted(): int
    {
        return \intval($this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->innerJoin('sgd.searchVolumeData', 'svd')
            ->where('svd.lastExtractionAt > 0')
            ->getQuery()
            ->getSingleScalarResult());
    }
}
