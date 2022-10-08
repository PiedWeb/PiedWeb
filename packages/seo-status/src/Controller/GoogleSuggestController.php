<?php

namespace PiedWeb\SeoStatus\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleSuggestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/google-suggest/{keyword}', methods: ['GET', 'HEAD', 'POST'], name: 'SearchGoogleSuggestRoute')]
    public function showGoogleSuggest(Request $request): Response
    {
        $keyword = \strval($request->get('keyword'));

        $keywords = explode(',', $keyword);
        foreach ($keywords as $k => $v) {
            $keywords[$k] = Search::normalizeKeyword($v);
        }

        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);
        // $search = $searchRepo->findOneBy(['keyword' => $keyword]);

        return $this->render('searchGoogleSuggests.html.twig', [
            'title' => $keyword.' - Google Suggest',
            'keyword' => $keyword,
            'search_value' => $keyword,
            // 'search' => $search,
        ]);
    }
}
