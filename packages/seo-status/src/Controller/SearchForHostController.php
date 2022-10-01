<?php

namespace PiedWeb\SeoStatus\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\SearchForHostRepository;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchForHostController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchForHostRepository $searchForHostRepo,
    ) {
    }

    #[Route('/host/{host}/{filters}', methods: ['GET', 'HEAD'], name: 'searchListForHostRoute')]
    public function showSearchListForHost(string $host, string $filters = ''): Response
    {
        if (Host::normalizeHost($host) !== $host) {
            $this->redirectToRoute('host', ['host' => Host::normalizeHost($host)]);
        }

        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        $hostObject = $hostRepo->findOneBy(['host' => $host]);

        if (null === $hostObject) {
            return $this->render('host.html.twig', [
                'title' => $host,
                'search_value' => $host,
                'host' => $hostObject,
            ]);
        }

        $searchList = $this->searchForHostRepo
            ->setFilters($filters)
            ->findSearchForHost($hostObject);
        $filters = $this->searchForHostRepo->getFilters();
        $this->searchForHostRepo->resetFilters();

        return $this->render('host.html.twig', [
            'title' => $host,
            'search_value' => $host,
            'host' => $hostObject,
            'filters' => $filters,
            'search_count' => [
                'organic' => $this->searchForHostRepo->countSearchOrganicFor($hostObject),
                'paid' => $this->searchForHostRepo->countSearchPaidFor($hostObject),
                'total' => $this->searchForHostRepo->countSearchFor($hostObject),
            ],
            'search_list' => $searchList,
        ]);
    }
}
