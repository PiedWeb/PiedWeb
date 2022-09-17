<?php

namespace PiedWeb\SeoStatus\Command;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Service\SearchExtractorService;
use PiedWeb\SeoStatus\Service\SearchImportJsonService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:extract')]
class SearchResultsExtractCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'search:extract';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchExtractorService $extractor,
        private SearchImportJsonService $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('keyword', InputArgument::OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var SearchRepository */
        $searchRepository = $this->entityManager->getRepository(Search::class);
        $keyword = $input->getArgument('keyword');
        $search = \is_string($keyword) ? $searchRepository->findOneBy(['keyword' => Search::normalizeKeyword($keyword)])
            : $searchRepository->findOneSearchToExtract();

        if (null === $search) {
            $output->writeln('Nothhing to extract');

            return Command::SUCCESS;
        }

        $output->writeln('Start extracting `'.(string) $search.'`');
        $jsonSearchResults = $this->extractor->extractGoogleResults($search);
        $searchResults = $this->importer->deserializeSearchResults($search, $jsonSearchResults);
        $this->entityManager->persist($searchResults);
        $this->entityManager->flush();
        $output->writeln(''.(string) $searchResults->getResults()->count().' results...');

        return Command::SUCCESS;
    }
}
