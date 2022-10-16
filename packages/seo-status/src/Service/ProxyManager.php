<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\Helper\Puphpeteer;
use PiedWeb\SeoStatus\Entity\Proxy;
use PiedWeb\SeoStatus\Repository\ProxyRepository;

final class ProxyManager
{
    private ?Proxy $proxy = null;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getProxy(): ?Proxy
    {
        return $this->proxy;
    }

    public function get(ExtendedClient|Puphpeteer $client): void
    {
        /** @var ProxyRepository */
        $proxyRepo = $this->entityManager->getRepository(Proxy::class);
        $this->proxy = $proxyRepo->findProxyReadyToUse();
        if (null === $this->proxy) {
            return;
        }

        // todo alert mail no more proxy

        $this->proxy->setLastUsedNow();
        if ($client instanceof ExtendedClient) {
            $client->setProxy($this->proxy->getProxy());
        } else {
            throw new Exception('Not yet implemented');
        }
    }
}
