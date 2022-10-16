<?php

namespace PiedWeb\SeoStatus\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\Twig\View\TwigView;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\SearchForHostRepository;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchForHostController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchForHostRepository $searchForHostRepo,
        private RouteGeneratorFactoryInterface $routeGeneratorFactory,
    ) {
    }

    #[Route('/host/{host}/{filters}', methods: ['GET', 'HEAD'], name: 'searchListForHostRoute')]
    public function showSearchListForHost(Request $request, string $host, string $filters = ''): Response
    {
        $view = 'searchListForHost.html.twig';

        if (Host::normalizeHost($host) !== $host) {
            $this->redirectToRoute('host', ['host' => Host::normalizeHost($host)]);
        }

        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        $hostObject = $hostRepo->findOneBy(['host' => $host]);

        if (null === $hostObject) {
            return $this->render($view, [
                'title' => $host,
                'search_value' => $host,
                'host' => $hostObject,
            ]);
        }

        $searchListQueryBuilder = $this->searchForHostRepo
            ->setFilters($filters)
            ->getQueryBuildeSearchForHost($hostObject);
        $pagerfanta = (new Pagerfanta(new QueryAdapter($searchListQueryBuilder)))
            ->setMaxPerPage(100)
            ->setCurrentPage(0 !== \intval($request->get('page')) ? \intval($request->get('page')) : 1);

        $filters = $this->searchForHostRepo->getFilters();
        $searchList = $pagerfanta->getCurrentPageResults();
        $this->searchForHostRepo->resetFilters();

        // $this->resetBadSearchResults($searchList);

        return $this->render($view, [
            'title' => $host,
            'search_value' => $host,
            'host' => $hostObject,
            'filters' => $filters,
            'search_count' => [
                'organic' => $this->searchForHostRepo->countSearchOrganicFor($hostObject),
                'paid' => $this->searchForHostRepo->countSearchPaidFor($hostObject),
                'total' => $this->searchForHostRepo->countSearchFor($hostObject),
                'organic_top' => [
                    1 => $this->searchForHostRepo->countSearchOrganicFor($hostObject, 1),
                    3 => $this->searchForHostRepo->countSearchOrganicFor($hostObject, 3),
                    5 => $this->searchForHostRepo->countSearchOrganicFor($hostObject, 5),
                    10 => $this->searchForHostRepo->countSearchOrganicFor($hostObject, 10),
                ],
            ],
            'search_list' => $searchList,
            'pagination_block' => (new TwigView($this->container->get('twig'), 'pager.html.twig')) // @phpstan-ignore-line
                            ->render($pagerfanta, $this->routeGeneratorFactory->create([]), []),
            'pager' => $pagerfanta,
        ]);
    }

    /**
     * @param Search[] $searchList
     */
    public function resetBadSearchResults(array $searchList): void
    {
        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);

        foreach ($searchList as $search) {
            $keyword = $search->getKeyword();
            $this->entityManager->remove($search);
            $this->entityManager->flush();
            $searchRepo->findOrCreate($keyword);
            $this->entityManager->flush();
        }
    }
}
