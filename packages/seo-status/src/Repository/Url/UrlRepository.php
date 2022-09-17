<?php

namespace PiedWeb\SeoStatus\Repository\Url;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use LogicException;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Entity\Url\Uri;
use PiedWeb\SeoStatus\Entity\Url\Url;

/**
 * @extends ServiceEntityRepository<Url>
 *
 * @method Url|null find($id, $lockMode = null, $lockVersion = null)
 * @method Url|null findOneBy(array $criteria, array $orderBy = null)
 * @method Url[]    findAll()
 * @method Url[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Url::class);
    }

    /** @var Url[] */
    private array $entityCache = [];

    public function findOrCreate(string $url): Url
    {
        $urlParts = \Safe\parse_url($url);
        if (! \is_array($urlParts)) {
            throw new Exception('Url `'.$url.'` can\'t be parsed');
        }

        $criteria = [
            'host' => isset($urlParts['host']) ? strtolower($urlParts['host']) : throw new LogicException($url),
            'uri' => ltrim($urlParts['path'], '/')
                .('' !== ($urlParts['query'] ?? '') ? '?'.$urlParts['query'] : ''),
            'schemeCode' => Url::retrieveShemeCode($urlParts['scheme']),
        ];

        $cleanedUrl = strtolower($urlParts['scheme']).'://'.$criteria['host'].'/'.$criteria['uri'];

        return $this->entityCache[$cleanedUrl] ??= $this->findOneBy($criteria)
        ?? $this->createUrl($criteria['host'], $criteria['uri'], $criteria['schemeCode']);
    }

    private function createUrl(string $host, string $uri, int $schemeCode): Url
    {
        $entity = (new Url())
            ->setHost($this->getEntityManager()->getRepository(Host::class)->findOrCreate($host))
            ->setUri($this->getEntityManager()->getRepository(Uri::class)->findOrCreate($host))
            ->setSchemeCode($schemeCode);
        $this->getEntityManager()->persist($entity);

        return $entity;
    }
}
