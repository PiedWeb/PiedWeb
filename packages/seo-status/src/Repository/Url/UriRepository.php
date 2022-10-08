<?php

namespace PiedWeb\SeoStatus\Repository\Url;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Url\Uri;

/**
 * @extends ServiceEntityRepository<Uri>
 *
 * @method Uri|null find($id, $lockMode = null, $lockVersion = null)
 * @method Uri|null findOneBy(array $criteria, array $orderBy = null)
 * @method Uri[]    findAll()
 * @method Uri[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UriRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Uri::class);
    }

    /** @var Uri[] */
    private array $index = [];

    public function resetIndex(): self
    {
        $this->index = [];

        return $this;
    }

    public function findOrCreate(string $uri): Uri
    {
        return $this->index[$uri] ??= $this->findOneBy(['uri' => $uri])
        ?? $this->create($uri);
    }

    private function create(string $uri): Uri
    {
        $entity = (new Uri())->setUri($uri);
        $this->getEntityManager()->persist($entity);

        return $entity;
    }
}
