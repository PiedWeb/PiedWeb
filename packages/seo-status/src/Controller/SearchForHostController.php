<?php

namespace PiedWeb\SeoStatus\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\SearchForHostRepository;
use PiedWeb\SeoStatus\Repository\SearchRepository;
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

        $searchList = $this->searchForHostRepo
            ->setFilters($filters)
            ->findSearchForHost($hostObject);
        $filters = $this->searchForHostRepo->getFilters();
        $this->searchForHostRepo->resetFilters();

        return $this->render($view, [
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

    /**
     * @param Search[] $searchList
     */
    public function resetBadSearchResults(array $searchList): void
    {
        $toAdd = [];
        foreach ($searchList as $search) {
            $last = $search->getSearchGoogleData()->getLastSearchResults();
            if (null !== $last
                && 'www.champsaur-valgaudemar.com' === $last->getResults()[0]->getHost()->getHost() // @phpstan-ignore-line
                && 'wildroad.fr' === $last->getResults()[1]->getHost()->getHost()) { // @phpstan-ignore-line
                dump($search->getKeyword());
                $toAdd[] = $search->getKeyword();
                $this->entityManager->remove($search);
            }
        }
        $this->entityManager->flush();

        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);

        foreach ($toAdd as $kw) {
            $searchRepo->findOrCreate($kw);
        }
        dd('exit');
    }
}
