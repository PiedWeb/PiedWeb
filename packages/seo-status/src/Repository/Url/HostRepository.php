<?php

namespace PiedWeb\SeoStatus\Repository\Url;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Url\Host;

/**
 * @extends ServiceEntityRepository<Host>
 *
 * @method Host|null find($id, $lockMode = null, $lockVersion = null)
 * @method Host|null findOneBy(array $criteria, array $orderBy = null)
 * @method Host[]    findAll()
 * @method Host[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Host::class);
    }

    /** @var Host[] */
    private array $index = [];

    public function resetIndex(): self
    {
        $this->index = [];

        return $this;
    }

    public function findOrCreate(string $host): Host
    {
        $host = strtolower($host);

        return $this->index[$host] ??= $this->findOneBy(['host' => $host])
        ?? $this->create($host);
    }

    private function create(string $host): Host
    {
        $entity = (new Host())->setHost($host);
        $this->getEntityManager()->persist($entity);

        return $entity;
    }
}
