<?php

namespace PiedWeb\SeoStatus\Command;

use DateTime;
use PiedWeb\Google\GoogleTrendsManager;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\TrendsTopic;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use PiedWeb\SeoStatus\Service\DataDirService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(name: 'search:extract-trends')]
class SearchExtractTrendsCommand extends SearchResultsExtractCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'search:extract-trends';

    protected function findSearchToExtract(): ?Search
    {
        return $this->searchRepo->findOneSearchTrendsToExtract();
    }

    protected function findSearchToExtractForHost(string $host): ?Search
    {
        /** @var HostRepository */
        $hostRepo = $this->entityManager->getRepository(Host::class);
        $hostEntity = $hostRepo->findOneBy(['host' => Host::normalizeHost($host)]);

        if (null === $hostEntity) {
            throw new NotFoundHttpException($host);
        }

        return $this->searchForHostRepo->findOneSearchTrendsToExtract($hostEntity);
    }

    protected function executeOnce(InputInterface $input, OutputInterface $output, ?Search $search = null): int
    {
        if (null === $search) {
            $output->writeln('Nothhing to extract');

            return Command::FAILURE;
        }

        $output->writeln('Start extracting `'.(string) $search.'`');
        $this->extractTrends($search);
        $search->getSearchVolumeData()->setLastExtractionAskedAt(0);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private DataDirService $dataDir;

    #[Required]
    public function setDataDir(DataDirService $dataDir): void
    {
        $this->dataDir = $dataDir;
    }

    private function extractTrends(Search $search): void
    {
        $trendsManager = new GoogleTrendsManager($search->getKeyword(), [$this->proxyManager, 'get']);
        $trendsManager->language = $search->getLang();
        $trendsManager->geo = $search->getGeo();
        $trendsManager->cacheFolder = $this->dataDir.'tmp';

        $searchVolumeData = $search->getSearchVolumeData();
        $extractor = $trendsManager->getExtractor();

        $topicList = $this->importToopics($extractor->getRelatedTopicsSimplified());
        $searchVolumeData->setLastExtractionAt((int) (new DateTime('now'))->format('ymdHi'));
        if (isset($topicList[0])) {
            $searchVolumeData->setMainRelatedTopic($topicList[0]);
        }
        $searchVolumeData->setRelatedSearches($extractor->getRelatedQueriesSimplified());
        $searchVolumeData->setRelatedTopics($extractor->getRelatedTopicsSimplified());
        $searchVolumeData->setVolume($extractor->getVolumeAverage());
        $searchVolumeData->setVolumeOverTheTime($extractor->getVolume());
    }

    /** @param array{'mid':string, 'title': string, 'type': string, 'value':int}[] $topics
     *
     * @return TrendsTopic[]
     */
    private function importToopics(array $topics): array
    {
        $topicList = [];
        foreach ($topics as $topic) {
            $topicList[] = (new TrendsTopic())
                ->setMid($topic['mid'])
                ->setTitle($topic['title'])
                ->setType($topic['type'])
            ;
        }

        return $topicList;
    }
}
