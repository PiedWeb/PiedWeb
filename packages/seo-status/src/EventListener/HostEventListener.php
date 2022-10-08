<?php

namespace PiedWeb\SeoStatus\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PiedWeb\Extractor\RegistrableDomain;
use PiedWeb\SeoStatus\Entity\Url\Domain;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\Url\DomainRepository;

#[AsEntityListener(event: Events::prePersist, entity: Host::class, method: 'setDomain')]
#[AsEntityListener(event: Events::preUpdate, entity: Host::class, method: 'setDomain')]
class HostEventListener
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function setDomain(Host $host): void
    {
        $domainStr = RegistrableDomain::get($host->getHost());
        /** @var DomainRepository */
        $repo = $this->entityManager->getRepository(Domain::class);
        $domain = $repo->findOrCreate($domainStr);
        $host->setDomain($domain);
    }
}
