<?php

namespace PiedWeb\SeoStatus\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AppController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    #[Route('/', name: 'redirectToSearchOrDomain', methods: ['POST'])]
    public function indexPost(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $query = trim(\strval($request->get('query', '')));
        $query = strtolower($query);
        $query = str_replace(['"', "'"], '', $query);
        /** @var string */
        $query = \Safe\preg_replace('/\s+/', ' ', $query);

        // Add filter_var URL and showUrl

        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        $host = Host::normalizeHost($query);
        if ('' !== $host && $hostRepo->findOneBy(['host' => $host])) {
            return $this->redirectToRoute('searchListForHostRoute', ['host' => $host]);
        }

        $keyword = Search::normalizeKeyword($query);

        return $this->redirectToRoute('search', ['keyword' => $keyword]);
    }

    #[Route('/search/{keyword}', methods: ['GET', 'HEAD'], name: 'search')]
    public function showSearch(string $keyword): Response
    {
        if (Search::normalizeKeyword($keyword) !== $keyword) {
            $this->redirectToRoute('search', ['keyword' => Search::normalizeKeyword($keyword)]);
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
}
