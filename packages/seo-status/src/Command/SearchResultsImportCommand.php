<?php

namespace PiedWeb\SeoStatus\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Repository\SearchResultsRepository;
use PiedWeb\SeoStatus\Service\DataDirService;
use PiedWeb\SeoStatus\Service\SearchImportJsonService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search-results:import')]
class SearchResultsImportCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'search-results:import';

    private string $from = 'local';

    private string $code = 'fr-fr';

    private int $limit = 3;

    private bool $importSearch = false;

    private OutputInterface $output;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchImportJsonService $importer,
        private DataDirService $dataDirService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, '', $this->from)
            ->addOption('code', 'c', InputOption::VALUE_REQUIRED, '', $this->code)
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '-1 no limit', $this->limit)
            ->addOption('import-search', null, InputOption::VALUE_NONE, '')
        ;
    }

    private function initOptions(InputInterface $input): void
    {
        $this->output->writeln('Starting...');
        $this->limit = \intval($input->getOption('limit'));
        $this->output->writeln('Limit : '.$this->limit);
        $this->from = \strval($input->getOption('from'));
        $this->output->writeln('From : '.$this->from);
        $this->code = \strval($input->getOption('code'));
        $this->output->writeln('code : '.$this->code);
        $this->importSearch = $input->hasOption('import-search');
    }

    private function getFileSystem(): \League\Flysystem\Filesystem
    {
        if ('local' !== $this->from) {
            throw new Exception('Not yet implemented');
        }

        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($this->dataDirService->get());

        return new \League\Flysystem\Filesystem($adapter);
    }

    private function getPatternToCheck(): string
    {
        $pattern = '#/(';

        if (-1 === $this->limit) {
            return $pattern.'[1-2][0-9][0-1]|0-9][0-3][0-9])#';
        }

        for ($i = 0; $i <= $this->limit; ++$i) {
            $pattern .= (int) (new DateTime('now'))->modify('-'.$i.' days')->format('ymd');
            $pattern .= '|';
        }

        return trim($pattern, '|').')#';
    }

    /**
     * @return array<string, DirectoryListing<FileAttributes>>
     */
    private function retrieveFileToImport(): array
    {
        $filesToImport = [];

        $searchDirs = $this->getFileSystem()->listContents($this->code)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isDir());

        foreach ($searchDirs as $searchDir) {
            if (! $searchDir instanceof \League\Flysystem\DirectoryAttributes) {
                continue;
            }

            $patternToCheck = $this->getPatternToCheck();
            $filesToImport[$searchDir->path()] = $this->getFileSystem()->listContents($searchDir->path())
                ->filter(fn (StorageAttributes $attributes) => $attributes->isFile() && \Safe\preg_match($patternToCheck, $attributes->path()));
        }

        return $filesToImport;
    }

    /**
     * @param DirectoryListing<FileAttributes> $listing
     */
    private function import(string $keywordDir, DirectoryListing $listing): void
    {
        /** @var string */
        $keyword = \Safe\preg_replace('#^'.$this->code.'/#', '', $keywordDir);

        $this->output->writeln('Importing  search results for `'.$keyword.'` ('.$this->code.')');
        /** @var SearchRepository */
        $searchRepository = $this->entityManager->getRepository(Search::class);
        /** @var SearchResultsRepository */
        $searchResultsRepository = $this->entityManager->getRepository(SearchResults::class);
        $search = $this->importSearch ? $searchRepository->findOrCreate($keyword)
            : $searchRepository->findOneBy(['keyword' => Search::normalizeKeyword($keyword)]);
        if (null === $search) {
            $this->output->writeln('`'.$keyword.'` (normalized as '.Search::normalizeKeyword($keyword).') not found when import SearchResults');

            return;
        }

        foreach ($listing as $file) {
            $extractedAt = basename($file->path(), '.json'); // Expected ymdHi
            $this->output->write($extractedAt);

            if ($sr = $searchResultsRepository->findOneBy(['extractedAt' => $extractedAt, 'searchGoogleData' => $search->getSearchGoogleData()])) {
                $this->output->writeln(' -- skipped');

                continue;
            }

            $this->output->writeln('');
            $jsonSearchResults = $this->getFileSystem()->read($file->path());
            $searchResults = $this->importer->deserializeSearchResults($search, $jsonSearchResults, $extractedAt);
            $this->entityManager->persist($searchResults);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->initOptions($input);

        $filesToImport = $this->retrieveFileToImport();
        foreach ($filesToImport as $keywordHash => $listing) {
            $this->import($keywordHash, $listing);
        }

        $this->entityManager->flush();
        $output->writeln('Done...');

        return Command::SUCCESS;
    }
}
