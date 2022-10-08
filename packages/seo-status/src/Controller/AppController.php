<?php

namespace PiedWeb\SeoStatus\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Url\Domain;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Entity\Url\Url;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Repository\Url\DomainRepository;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use PiedWeb\SeoStatus\Repository\Url\UrlRepository;
use PiedWeb\SeoStatus\Service\DataDirService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class AppController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private DataDirService $dataDirService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $parameters = [];

        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);
        $parameters['numberSearchToExtract'] = $searchRepo->countSearchToExtract();
        $parameters['numberSearchCount'] = $searchRepo->countSearch();
        $parameters['numberSearchExtractedCount'] = $parameters['numberSearchCount'] - $parameters['numberSearchToExtract'];

        /** @var DomainRepository */
        $domainRepo = $this->entityManager->getRepository(Domain::class);
        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        /** @var UrlRepository */
        $urlRepo = $this->entityManager->getRepository(Url::class);
        $parameters['numberHost'] = \intval($hostRepo->createQueryBuilder('h')->select('count(h.id)')->getQuery()->getSingleScalarResult());
        $parameters['numberUrl'] = \intval($urlRepo->createQueryBuilder('u')->select('count(u.id)')->getQuery()->getSingleScalarResult());
        $parameters['numberDomain'] = \intval($domainRepo->createQueryBuilder('d')->select('count(d.id)')->getQuery()->getSingleScalarResult());
        $parameters['numberUrlExtracted'] = 0;

        return $this->render('index.html.twig', $parameters);
    }

    private function getReferringRouteMainParameter(string $referringRoute): string
    {
        if ('' === $referringRoute) {
            return '';
        }

        $referringRoute = $this->router->getRouteCollection()->get($referringRoute) ?? throw new Exception($referringRoute);
        $referringRouteDefaults = array_keys($referringRoute->getDefaults());
        $referringRoutePathVariables = array_filter($referringRoute->compile()->getPathVariables(), fn ($value) => ! \in_array($value, $referringRouteDefaults));

        return 1 === \count($referringRoutePathVariables) ? $referringRoutePathVariables[0] : '';
    }

    private function cleanQuery(mixed $query): string
    {
        $query = trim(\strval($query));
        $query = strtolower($query);
        $query = str_replace(['"', "'"], '', $query);
        /** @var string */
        $query = \Safe\preg_replace('/\s+/', ' ', $query);

        return $query;
    }

    #[Route('/', name: 'redirectToSearchOrDomain', methods: ['POST'])]
    public function indexPost(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $referringRoute = \strval($request->get('referring_route', ''));
        $referringRouteMainParameter = $this->getReferringRouteMainParameter($referringRoute);

        $query = $this->cleanQuery($request->get('query', ''));

        // Add filter_var URL and showUrl

        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        $host = Host::normalizeHost($query);
        if ('' !== $host) {
            return $this->redirectToRoute(
                'host' === $referringRouteMainParameter ? $referringRoute : 'searchListForHostRoute',
                ['host' => $host]
            );
        }

        $keyword = Search::normalizeKeyword($query);

        return $this->redirectToRoute(
            'keyword' === $referringRouteMainParameter ? $referringRoute : 'searchRoute',
            ['keyword' => $keyword]
        );
    }

    #[Route('/search/{keyword}', methods: ['GET', 'HEAD'], name: 'searchRoute')]
    public function showSearch(string $keyword): Response
    {
        if (Search::normalizeKeyword($keyword) !== $keyword) {
            return $this->redirectToRoute('searchRoute', ['keyword' => Search::normalizeKeyword($keyword)]);
        }

        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);
        $search = $searchRepo->findOneBy(['keyword' => $keyword]);

        return $this->render('search.html.twig', [
            'title' => $keyword,
            'keyword' => $keyword,
            'search_value' => $keyword,
            'search' => $search,
        ]);
    }

    #[Route('/search-cache/{keyword}', methods: ['GET', 'HEAD'], name: 'searchCacheRoute')]
    public function showSearchCache(string $keyword): Response|NotFoundHttpException
    {
        if (Search::normalizeKeyword($keyword) !== $keyword) {
            return $this->redirectToRoute('searchRoute', ['keyword' => Search::normalizeKeyword($keyword)]);
        }
        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);
        $search = $searchRepo->findOneBy(['keyword' => $keyword]);

        if (null === $search || ! file_exists($this->dataDirService->getSearchDir($search).'lastResult.html')) {
            return $this->createNotFoundException();
        }

        $cacheContent = \Safe\file_get_contents($this->dataDirService->getSearchDir($search).'lastResult.html');
        $cacheContent = \Safe\preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $cacheContent);
        /** @var string */
        $cacheContent = \Safe\preg_replace('/<noscript\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/noscript>/i', '', $cacheContent);

        return new Response($cacheContent);
    }
}
