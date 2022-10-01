<?php

namespace PiedWeb\SeoStatus\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Exception;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Url\Host;

class SearchForHostRepository
{
    /** @var EntityRepository<Search> */
    private EntityRepository $repository;

    /**
     * @var array<array{k: string, o: string, v: string}>
     */
    private array $filters = [];

    /** @var array<string, string> */
    private array $orderBy = ['r.pos' => 'ASC'];

    public const ORDER_KEYS = ['s.keyword', 'r.pixelPos', 'r.pos'];

    public const SEARCH_KEYS = ['s.keyword', 'r.pixelPos', 'r.pos'];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Search::class);
    }

    public function setFilters(string $filters): self
    {
        if ('' === $filters) {
            return $this;
        }

        /** @var array<string, array<string, string>|array{k: string, o: string, v: string}> */
        $filters = \Safe\json_decode(\Safe\base64_decode($filters), true);

        if (isset($filters['orderBy'])) {
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
            unset($filters['orderBy']);
        }

        foreach ($filters as $filterKey => $filter) {
            if (
                array_keys($filter) !== ['k', 'o', 'v']
                || ! \in_array($filter['k'], self::SEARCH_KEYS)
                || ! \in_array($filter['o'], ['=', '>=', '<=', '<', '>', 'LIKE', '<>'])
                || ! \is_string($filter['v'])
            ) {
                throw new Exception();
            }
        }

        $this->filters = array_values($filters); // @phpstan-ignore-line

        return $this;
    }

    /**
     * @return array<string, array<string, string>|array{k: string, o: string, v: string}>
     */
    public function getFilters(): array
    {
        $filters = [];
        foreach ($this->filters as $k => $f) {
            $filters[$f['k']] = $f;
        }

        $filters['orderBy'] = $this->orderBy;

        return $filters;
    }

    public function resetFilters(): void
    {
        $this->filters = [];
    }

    public function getQueryBuildeSearchForHost(Host $host): QueryBuilder
    {
        $queryBuilder = $this->repository->createQueryBuilder('s')
            ->innerJoin('s.searchGoogleData', 'sgd')
            ->innerJoin('sgd.lastSearchResults', 'sr')
            ->innerJoin('sr.results', 'r')
            ->where('r.host = '.$host->getId());

        if ([] !== $this->filters) {
            foreach ($this->filters as $filter) {
                $paramKey = 'param'.substr(sha1(implode(',', $filter)), 0, 6);
                $queryBuilder->andWhere($filter['k'].' '.$filter['o'].' :'.$paramKey)
                    ->setParameter(
                        $paramKey,
                        'LIKE' === $filter['o'] ? '%'.$filter['v'].'%' : $filter['v']
                    );
            }
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
            ->add('orderBy', $this->getOrderByFlatten())
            ->getQuery()
            ->getResult();

        return $results;
    }

    private function getOrderByFlatten(): string
    {
        $return = '';

        foreach ($this->orderBy as $col => $dir) {
            $return .= $col.' '.$dir.',';
        }

        return trim($return, ',');
    }

    public function countSearchOrganicFor(Host $host): int
    {
        return \intval($this->getQueryBuildeSearchForHost($host)
            ->select('COUNT(s.id)')
            ->andWhere('r.ads = false')
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
}
