<?php

namespace PiedWeb\SeoStatus\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchVolumeData;
use PiedWeb\SeoStatus\Entity\Url\Host;

class SearchForHostRepository
{
    private SearchRepository $repository;

    /**
     * @var array{k: string, o: string, v: string}[]
     */
    private array $where = [];

    /** @var array<string, string> */
    private array $orderBy = ['r.pos' => 'ASC'];

    public const ORDER_KEYS = [
        's.keyword',
        'r.pixelPos',
        'r.pos',
        'r.movement',
        'sr.extractedAt',
        'svd.volume',
        'mrt.title',
    ];

    public const SEARCH_KEYS = [
        's.keyword',
        'r.pixelPos',
        'r.pos',
        'r.ads',
        'r.movement',
        'sr.serpFeatures',
        'svd.volume',
        'mrt.title',
        'svd.relatedTopics',
    ];

    public const OPERATOR_LIST = ['NOT LIKE', 'LIKE', '<>', '!=', '>=', '<=', '<', '>', '=', '!'];

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(Search::class);
    }

    /** @param array<mixed> $filters */
    private function managerOrderByFilters(array $filters): void
    {
        if (! isset($filters['orderBy'])) {
            return;
        }

        if (! \is_array($filters['orderBy'])) {
            throw new Exception();
        }

        foreach ($filters['orderBy'] as $orderKey => $orderDirection) {
            if (! \in_array($orderKey, self::ORDER_KEYS)) {
                throw new Exception($orderKey);
            }

            if (! \in_array($orderDirection, ['ASC', 'DESC'])) {
                throw new Exception();
            }
        }

        $this->orderBy = $filters['orderBy'];
    }

    /** @param array<mixed> $filters */
    private function manageWhereFilters(array $filters): void
    {
        if (! isset($filters['where']) || ! \is_array($filters['where'])) {
            return;
        }

        foreach ($filters['where'] as $key => $where) {
            if (
                array_keys($where) !== ['k', 'o', 'v']
                || ! \in_array($where['k'], self::SEARCH_KEYS)
                || ! \in_array($where['o'], self::OPERATOR_LIST)
                || (! \is_string($where['v']) && ! \is_int($where['v']))
            ) {
                throw new Exception($key.' '.\Safe\json_encode($where));
            }
        }

        $this->where = array_values($filters['where']);
    }

    public function setFilters(string $filters): self
    {
        if ('' === $filters) {
            return $this;
        }

        /** @var array<mixed> */
        $filters = \Safe\json_decode(urldecode(\Safe\base64_decode($filters)), true);
        $this->managerOrderByFilters($filters);
        $this->manageWhereFilters($filters);

        return $this;
    }

    /** @return array{'orderBy': array<string, string>, 'where': array{k: string, o: string, v: string}[]} */
    public function getFilters(): array
    {
        $filters = ['where' => []];
        foreach ($this->where as $k => $f) {
            $filters['where'][$f['k']] = $f;
        }

        $filters['orderBy'] = $this->orderBy;

        return $filters;
    }

    public function resetFilters(): void
    {
        $this->where = [];
    }

    public function getQueryBuildeSearchForHost(Host $host): QueryBuilder
    {
        $queryBuilder = $this->repository->createQueryBuilder('s')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->innerJoin('sgd.lastSearchResults', 'sr')
            ->innerJoin('sgd.searchVolumeData', 'svd')
            ->leftJoin('svd.mainRelatedTopic', 'mrt')
            ->innerJoin('sr.results', 'r')
            ->where('r.host = '.$host->getId())
            ->add('orderBy', '' !== $this->getOrderByFlatten() ? $this->getOrderByFlatten() : 's.keyword ASC');
        foreach ($this->where as $where) {
            $where['o'] = '!' === $where['o'] ? 'NOT LIKE' : $where['o'];
            $paramKey = 'param'.substr(sha1(implode(',', $where)), 0, 6);
            $queryBuilder->andWhere($where['k'].' '.$where['o'].' :'.$paramKey)
                ->setParameter(
                    $paramKey,
                    str_contains($where['o'], 'LIKE') ? '%'.trim($where['v']).'%' : $where['v']
                );
        }

        return $queryBuilder;
    }

    /**
     * @return Search[]
     */
    public function findSearchForHost(Host $host): array
    {
        /** @var Search[] */
        $results = $this->getQueryBuildeSearchForHost($host)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $results;
    }

    private function getOrderByFlatten(): string
    {
        $return = '';

        foreach ($this->orderBy as $col => $dir) {
            if ('sr.extractedAt' === $col) {
                $col = 'SUBSTRING(sr.extractedAt, 1, 6)';
            }

            $return .= $col.' '.$dir.',';
        }

        return trim($return, ',');
    }

    public function countSearchOrganicFor(Host $host, int $top = 0): int
    {
        $qb = $this->getQueryBuildeSearchForHost($host)
            ->select('COUNT(s.id)')
            ->andWhere('r.ads = false');

        if ($top > 0) {
            $qb->andWhere('r.pos <= '.$top);
        }

        return \intval($qb
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function countSearchPaidFor(Host $host): int
    {
        return \intval($this->getQueryBuildeSearchForHost($host)
            ->select('COUNT(s.id)')
            ->andWhere('r.ads = true')
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function countSearchFor(Host $host): int
    {
        return \intval($this->getQueryBuildeSearchForHost($host)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult());
    }

    private function forHost(Host $host, QueryBuilder $qb): QueryBuilder
    {
        return $qb->innerJoin('sgd.lastSearchResults', 'sr')
                    ->innerJoin('sr.results', 'r')
                    ->where('r.host = '.$host->getId());
    }

    public function findOneSearchToExtract(Host $host): ?Search
    {
        /** @var ?Search */
        $search = $this->forHost($host, $this->repository->getQueryToFindSearchToExtract(1))
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $search) {
            $this->repository->updateLastExtractionAksedAt($search);
        }

        return $search;
    }

    public function findOneSearchTrendsToExtract(Host $host): ?Search
    {
        /** @var ?Search */
        $search = $this->forHost($host, $this->repository->getQueryToFindSearchTrendsToExtract())
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $search) {
            $this->repository->updateLastExtractionAksedAt($search, SearchVolumeData::class);
        }

        return $search;
    }
}
