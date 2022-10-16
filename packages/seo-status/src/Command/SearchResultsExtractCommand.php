<?php

namespace PiedWeb\SeoStatus\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\Google\Helper\Puphpeteer;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\SearchForHostRepository;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use PiedWeb\SeoStatus\Service\ProxyManager;
use PiedWeb\SeoStatus\Service\SearchExtractorService;
use PiedWeb\SeoStatus\Service\SearchImportJsonService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsCommand(name: 'search:extract')]
class SearchResultsExtractCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'search:extract';

    protected Puphpeteer $puphpeteer;

    protected SearchRepository $searchRepo;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected SearchExtractorService $extractor,
        protected SearchImportJsonService $importer,
        protected SearchForHostRepository $searchForHostRepo,
        protected ProxyManager $proxyManager
    ) {
        parent::__construct();

        $this->puphpeteer = new Puphpeteer();
        $this->searchRepo = $this->entityManager->getRepository(Search::class);
    }

    public function __destruct()
    {
        $this->puphpeteer->closeAll();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('keyword', InputArgument::OPTIONAL)
            ->addOption('disable-import', null, InputOption::VALUE_NONE, '')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '', 1)
            ->addOption('sleep', 's', InputOption::VALUE_REQUIRED, '', 10)
            // ->addArgument('lang', InputArgument::OPTIONAL, '', 'fr') // if lang is langAndTld is Setted, getEntityClassFromLocaleCode...
            // ->addArgument('tld', InputArgument::OPTIONAL, '', 'fr')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initCommand(); // Avoid error on command executing multiple time (eg: test)

        $limit = \intval($input->getOption('limit'));

        if (1 === $limit) {
            return $this->executeOnce($input, $output, $this->getSearch($input)) ?? Command::SUCCESS;
        }

        $startTime = time();
        $sleep = \intval($input->getOption('sleep'));
        $output->writeln('');
        $output->writeln('Try to extract '.$limit.' searches with waiting ~'.$sleep.' between each request.');
        $output->writeln('');
        for ($i = 1; $limit >= $i; ++$i) {
            $executed = $this->executeOnce($input, $output, $this->getSearch($input));
            if (Command::SUCCESS !== $executed) {
                return $executed ?? Command::SUCCESS;
            }

            $this->managePause($sleep, $output);

            $duration = time() - $startTime;
            $output->writeln('s'.$sleep.' | '.gmdate("i's", $duration).' | '.round($duration / $i).' | '.round(($duration + 150) / $i).' | '.$i);
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function initCommand(): void
    {
        $this->keywordList = null;
        $this->keywordListIteratorKey = 0;
    }

    /** @var string[]|null */
    private ?array $keywordList = null;

    private int $keywordListIteratorKey = 0;

    protected function findSearchToExtract(): ?Search
    {
        return $this->searchRepo->findOneSearchToExtract();
    }

    protected function findSearchToExtractForHost(string $host): ?Search
    {
        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        $hostEntity = $hostRepo->findOneBy(['host' => Host::normalizeHost($host)]);

        if (null === $hostEntity) {
            throw new NotFoundHttpException($host);
        }

        return $this->searchForHostRepo->findOneSearchToExtract($hostEntity);
    }

    private function getSearch(InputInterface $input): ?Search
    {
        if (! \is_string($input->getArgument('keyword'))) {
            return $this->findSearchToExtract();
        }

        if (str_starts_with($input->getArgument('keyword'), 'forHost:')) {
            return $this->findSearchToExtractForHost(substr($input->getArgument('keyword'), \strlen('forHost:')));
        }

        $this->keywordList ??= explode(\chr(10), $input->getArgument('keyword'));

        $keyword = $this->keywordList[$this->keywordListIteratorKey] ?? null;

        $search = null === $keyword ? null : $this->searchRepo->findOrCreate($keyword);
        ++$this->keywordListIteratorKey;

        return $search;
    }

    private function managePause(int $sleep, OutputInterface $output): void
    {
        if (0 === $sleep) {
            return;
        }

        $sleepTime = random_int((int) round($sleep * 0.5), (int) round($sleep * 1.5));
        $output->writeln('Waiting '.$sleepTime.'s...');
        sleep($sleepTime);
    }

    protected function executeOnce(InputInterface $input, OutputInterface $output, ?Search $search = null): ?int
    {
        if (null === $search) {
            $output->writeln('Nothhing to extract');

            return null;
        }

        $output->writeln('Start extracting `'.(string) $search.'`');
        $jsonSearchResults = $this->extractor->extractGoogleResults($search);

        if (! $input->getOption('disable-import')) {
            $lastExtractionAskedAt = $search->getSearchGoogleData()->getLastExtractionAskedAt();
            $search->getSearchGoogleData()->setLastExtractionAskedAt(0);
            $searchResults = $this->importer->deserializeSearchResults($search, $jsonSearchResults, $lastExtractionAskedAt);
            if (null === $searchResults) {
                throw new Exception();
            }

            $this->entityManager->persist($searchResults);
            $this->entityManager->flush();
            $output->writeln(''.(string) $searchResults->getResults()->count().' results imported in database.');
        }

        return Command::SUCCESS;
    }
}
