<?php

namespace PiedWeb\SeoStatus\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PiedWeb\SeoStatus\Entity\Search\TrendsTopic;

/**
 * @extends ServiceEntityRepository<TrendsTopic>
 *
 * @method TrendsTopic|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrendsTopic|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method TrendsTopic[]    findAll()
 * @method TrendsTopic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrendsTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrendsTopic::class);
    }

    /** @var array<string, ?TrendsTopic> */
    private array $index = [];

    public function resetIndex(): self
    {
        $this->index = [];

        return $this;
    }

    public function findOrCreate(string $mid, string $title, string $type, bool &$creation = false): TrendsTopic
    {
        if (isset($this->index[$mid]) && null !== $this->index[$mid]) {
            return $this->index[$mid];
        }

        $trendsTopic = $this->findOneBy(['mid' => $mid]);
        if (null === $trendsTopic) {
            $creation = true;
            $trendsTopic = (new trendsTopic())
                ->setMid($mid)
                ->setTitle($title)
                ->setType($type);
            $this->getEntityManager()->persist($trendsTopic);
        }

        return $this->index[$mid] = $trendsTopic;
    }
}
