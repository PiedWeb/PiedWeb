<?php

namespace PiedWeb\SeoStatus\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Proxy;

/**
 * @extends ServiceEntityRepository<Proxy>
 *
 * @method Proxy[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProxyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proxy::class);
    }

    public function findProxyReadyToUse(): ?Proxy
    {
        return $this->createQueryBuilder('p')  // @phpstan-ignore-line
            ->where('p.googleBlacklist = true')
            ->orWhere('p.lastUsedAt >= :yesterday')
            ->setParameter('yesterday', new \DateTime('yesterday'), 'datetime')
            ->orderBy('p.lastUsedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
