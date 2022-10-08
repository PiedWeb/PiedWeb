<?php

namespace PiedWeb\SeoStatus\Repository\Url;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Url\Domain;

/**
 * @extends ServiceEntityRepository<Domain>
 *
 * @method Domain|null find($id, $lockMode = null, $lockVersion = null)
 * @method Domain|null findOneBy(array $criteria, array $orderBy = null)
 * @method Domain[]    findAll()
 * @method Domain[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    /** @var Domain[] */
    private array $index = [];

    public function resetIndex(): self
    {
        $this->index = [];

        return $this;
    }

    public function findOrCreate(string $domain): Domain
    {
        $domain = strtolower($domain);

        return $this->index[$domain] ??= $this->findOneBy(['domain' => $domain])
        ?? $this->create($domain);
    }

    private function create(string $domain): Domain
    {
        $entity = (new Domain())->setDomain($domain);
        $this->getEntityManager()->persist($entity);

        return $entity;
    }
}
