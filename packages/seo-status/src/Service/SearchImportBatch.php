<?php

namespace PiedWeb\SeoStatus\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;
use PiedWeb\SeoStatus\Entity\Url\Domain;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Entity\Url\Uri;
use PiedWeb\SeoStatus\Entity\Url\Url;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Repository\SearchResultsRepository;
use Psr\Log\LoggerInterface;

final class SearchImportBatch
{
    /**
     * @var array<string, FileAttributes[]>|null
     */
    private ?array $filesToImport = null;

    private SearchResultsRepository $searchResultsRepo;

    private SearchRepository $searchRepo;

    public int $limit = -1;

    public string $code = 'fr-fr';

    public string $from = 'local';

    public int  $maxSearchesToImportBeforeFlush = 500;

    private int $searchesImported = 0;

    /** @var ?callable */
    public $freezeCallback = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchImportJsonService $importer,
        private DataDirService $dataDirService,
        private ?LoggerInterface $logger = null,
    ) {
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->searchResultsRepo = $this->entityManager->getRepository(SearchResults::class);
        $this->searchRepo = $this->entityManager->getRepository(Search::class);
    }

    private function getPatternForSearchResultsJsonFilename(): string
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

    /** @return array<string, FileAttributes[]> */
    public function getFilesToImport(): array
    {
        if (null !== $this->filesToImport) {
            return $this->filesToImport;
        }

        $filesToImport = [];

        $searchDirs = $this->getFileSystem()->listContents($this->code)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isDir());

        foreach ($searchDirs as $searchDir) {
            if (! $searchDir instanceof \League\Flysystem\DirectoryAttributes) {
                continue;
            }

            $patternToCheck = $this->getPatternForSearchResultsJsonFilename();

            $filesToImport[$searchDir->path()] = $this->getFileSystem()->listContents($searchDir->path())
                ->filter(fn (StorageAttributes $attributes) => $attributes->isFile() && \Safe\preg_match($patternToCheck, $attributes->path()))
                ->sortByPath()
                ->toArray();
        }

        return $this->filesToImport = $filesToImport; // @phpstan-ignore-line
    }

    public function importSearches(): void
    {
        $filesToImport = $this->getFilesToImport();
        $this->searchRepo->loadIndex();
        foreach ($filesToImport as $keywordDir => $listing) {
            $this->importSearch($keywordDir);
            $this->freeMemory();
        }

        $this->freeMemory();
        $this->searchesImported = 0;
    }

    private function getKeywordFromDir(string $keywordDir): string
    {
        /** @var string */
        $keyword = \Safe\preg_replace('#^'.$this->code.'/#', '', $keywordDir);
        $keyword = Search::normalizeKeyword($keyword);

        return $keyword;
    }

    public function importSearch(string $keywordDir): void
    {
        $keyword = $this->getKeywordFromDir($keywordDir);
        $this->importer->importSearch($keyword, true);
    }

    private function getFileSystem(): \League\Flysystem\Filesystem
    {
        if ('local' !== $this->from) {
            throw new Exception('Not yet implemented');
        }

        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($this->dataDirService->get());

        return new \League\Flysystem\Filesystem($adapter);
    }

    public function importSearchResultsBatch(): void
    {
        $filesToImport = $this->getFilesToImport();

        $filesToImport = array_filter($filesToImport, fn ($fileListing) => [] !== $fileListing);
        foreach ($filesToImport as $keywordDir => $fileListing) {
            $this->importSearchResultsBatchForSearch($keywordDir, $fileListing);
            $this->freeMemory();
        }

        $this->entityManager->flush();
    }

    /** @param FileAttributes[] $fileListing */
    public function importSearchResultsBatchForSearch(string $keywordDir, array $fileListing): void
    {
        $keyword = $this->getKeywordFromDir($keywordDir);

        $search = $this->searchRepo->findOneByKeyword($keyword);

        if (null === $search) {
            if (null != $this->logger) {
                $this->logger->warning('`'.$keyword.'` not found when import SearchResults');
            }

            return;
        }

        foreach ($fileListing as $file) {
            $this->importSearchResults($file, $search);
        }
    }

    public function importSearchResults(FileAttributes $file, Search $search): void
    {
        $extractedAt = (int) basename($file->path(), '.json'); // Expected ymdHi

        if ($this->searchResultsRepo->findOneBy([
            'extractedAt' => $extractedAt,
            'searchGoogleData' => $search->getSearchGoogleData(),
        ])) {
            return;
        }

        $jsonSearchResults = $this->getFileSystem()->read($file->path());
        $searchResults = $this->importer->deserializeSearchResults($search, $jsonSearchResults, $extractedAt);
        if (null === $searchResults) {
            if (null != $this->logger) {
                $this->logger->warning($search->getKeyword().' : '.$extractedAt.' malformed search results');
            }

            return;
        }

        if ('local' !== $this->from) {
            \Safe\file_put_contents($this->dataDirService->getSearchDir($search).$extractedAt.'.json', $jsonSearchResults);
        }

        $this->entityManager->persist($searchResults);
    }

    public function freeMemory(): void
    {
        ++$this->searchesImported;

        $modulo = $this->searchesImported % $this->maxSearchesToImportBeforeFlush;
        if (0 !== $modulo) { // or memory_get_usage()> ...
            return;
        }

        if ($this->freezeCallback) {
            \call_user_func($this->freezeCallback, $this->searchesImported);
        }

        $this->entityManager->flush();
        $this->entityManager->getRepository(Search::class)->resetIndex();
        $this->entityManager->getRepository(Uri::class)->resetIndex();
        $this->entityManager->getRepository(Url::class)->resetIndex();
        $this->entityManager->getRepository(Host::class)->resetIndex();
        $this->entityManager->getRepository(Domain::class)->resetIndex();
        $this->entityManager->clear();
        gc_collect_cycles();
        $this->searchRepo->loadIndex();
    }
}
