<?php

namespace PiedWeb\SeoStatus\Tests\Service;

use Doctrine\ORM\EntityManager;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchGoogleData;
use PiedWeb\SeoStatus\Entity\Search\SearchResult;
use PiedWeb\SeoStatus\Entity\Search\SearchResults;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Entity\Url\Url;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use PiedWeb\SeoStatus\Service\DataDirService;
use PiedWeb\SeoStatus\Service\SearchExtractorService;
use PiedWeb\SeoStatus\Service\SearchImportJsonService;
use PiedWeb\SeoStatus\Service\SearchResultsComparator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class SearchServicesTest extends KernelTestCase
{
    private ?string $json = null;

    private function getSearch(): Search
    {
        $search = $this->getEntityManager()->getRepository(Search::class)
            ->findOneBy(['keyword' => 'pied web']);

        if (null !== $search) {
            return $search;
        }

        $search = new Search();
        $search->setKeyword('Pied Web');
        $search->getSearchGoogleData()->setCpc(1250);
        $this->getEntityManager()->persist($search);
        $this->getEntityManager()->flush();

        return $search;
    }

    private function getEntityManager(): EntityManager
    {
        return self::getContainer()->get('doctrine.orm.entity_manager'); // @phpstan-ignore-line
    }

    private function getDataDirService(): DataDirService
    {
        /** @var string */
        $appDataDir = self::getContainer()->getParameter('app.dataDir');

        return new DataDirService('test', $appDataDir);
    }

    private function getExtractor(): SearchExtractorService
    {
        return new SearchExtractorService(
            $this->getDataDirService()
        );
    }

    private function getJsonFromExtractorService(): string
    {
        if (null !== $this->json) {
            return $this->json;
        }

        $search = $this->getSearch();

        return $this->json = $this->getExtractor()->extractGoogleResults($search);
    }

    public function testNormalizeKeuword(): void
    {
        $this->assertSame('test 1', Search::normalizeKeyword('test_1'));
        $this->assertSame('test 1', Search::normalizeKeyword('test-1'));
    }

    public function testFindSearchToExtract(): void
    {
        /** @var SearchRepository */
        $searchRepository = $this->getEntityManager()->getRepository(Search::class);

        $search = $this->getSearch();
        $search->getSearchGoogleData()
            ->setExtractionFrequency(SearchGoogleData::ExtractionFrequency['daily']);

        $search->getSearchGoogleData()
            ->setNextExtractionFrom((int) (new \DateTime('now'))->modify('-2 days')->format('ymdHi'));
        $this->getEntityManager()->flush();
        $this->assertNotNull($searchRepository->findOneSearchToExtract());

        $search->getSearchGoogleData()
            ->setNextExtractionFrom((int) (new \DateTime('now'))->modify('+2 days')->format('ymdHi'));
        $this->getEntityManager()->flush();
        if (($search = $searchRepository->findOneSearchToExtract()) !== null) {
            dd($search->getKeyword());
        }
        $this->assertNull($search); // @phpstan-ignore-line
    }

    public function testSearchExtractorService(): void
    {
        $json = $this->getJsonFromExtractorService();
        $json = json_decode($json);
        $this->assertSame('1', $json->version); // @phpstan-ignore-line
    }

    private function getImporter(): SearchImportJsonService
    {
        return new SearchImportJsonService(
            $this->getEntityManager(),
            self::getContainer()->get(SerializerInterface::class), // @phpstan-ignore-line
            $this->getDataDirService()
        );
    }

    public function testSearchImporterJsonService(): void
    {
        $json = $this->getJsonFromExtractorService();

        $search = $this->getSearch();
        $searchResults = $this->getImporter()->deserializeSearchResults(
            $search,
            $json,
            $search->getSearchGoogleData()->getLastExtractionAskedAt()
        );

        $this->assertInstanceOf(SearchResults::class, $searchResults);
        $this->assertSame($this->getSearch(), $searchResults->getSearchGoogleData()->getSearch());
        $this->assertTrue($searchResults->getResults()->filter(function (SearchResult $searchResult) {
            return 'piedweb.com' === (string) $searchResult->getHost();
        })->count() >= 1);

        $this->getEntityManager()->persist($searchResults);
        $this->getEntityManager()->flush();

        /** @var Host */
        $host = $this->getEntityManager()->getRepository(Host::class)->findOneBy(['host' => 'piedweb.com']);
        /** @var Url */
        $url = $this->getEntityManager()->getRepository(Url::class)->findOneBy(['host' => $host]);
        /** @var SearchResult */
        $searchResult = $this->getEntityManager()->getRepository(SearchResult::class)
            ->findOneBy(['url' => $url->getId()]);
        $this->assertSame($searchResults, $searchResult->getSearchResults());

        $search->getSearchGoogleData()->getSearchVolumeData()->setVolume(1290);
        $this->assertSame(1290, $search->getSearchGoogleData()->getSearchVolumeData()->getVolume());
        $search->disableExport = false;
        $this->getEntityManager()->flush();
        $this->assertSame(1290, $search->getSearchGoogleData()->getSearchVolumeData()->getVolume());

        $this->getImporter()->importSearch($search);
        $this->assertSame(1290, $search->getSearchGoogleData()->getSearchVolumeData()->getVolume());
    }

    public function testImportSearch(): void
    {
        $search = $this->getSearch();
        $this->getEntityManager()->remove($search);
        $this->getEntityManager()->flush();

        $json = $this->getExtractor()->searchToJson($search);
        $importedSearch = new Search();
        $this->getImporter()->deserializeSearch($json, $importedSearch);

        $this->assertSame($search->getSearchGoogleData()->getCpc(), $importedSearch->getSearchGoogleData()->getCpc());
        $this->assertSame($importedSearch, $importedSearch->getSearchGoogleData()->getSearch());
    }

    public function testSimilar(): void
    {
        $kw1 = 'pizza avignon';
        $kw2 = 'pizzas avignon';

        $search1 = (new Search())->setKeyword($kw1);
        $search2 = (new Search())->setKeyword($kw2);
        $this->getEntityManager()->persist($search1);
        $this->getEntityManager()->persist($search2);
        $this->getEntityManager()->flush();

        $application = new Application(self::bootKernel());
        $command = $application->find('search:extract');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['keyword' => $kw1]);
        $commandTester->assertCommandIsSuccessful();
        $commandTester->execute(['keyword' => $kw2]);
        $commandTester->assertCommandIsSuccessful();

        /** @var Search */
        $search1 = $this->getEntityManager()->getRepository(Search::class)->findOneBy(['keyword' => $kw1]);
        /** @var Search */
        $search2 = $this->getEntityManager()->getRepository(Search::class)->findOneBy(['keyword' => $kw2]);
        $comparator = new SearchResultsComparator($this->getEntityManager());
        $similarityScolre = $comparator->getSimilarityScore($search1, $search2);
        $this->assertGreaterThan(25, (int) ($similarityScolre * 100));
        $areSimilar = $comparator->areSimilar($search1, $search2);
        $this->assertTrue($areSimilar);
    }

    public function testImportCommand(): void
    {
        $fs = new Filesystem();
        $fs->mirror(__DIR__.'/../searchData/fr-fr/huitre', __DIR__.'/../../var/data/test/search/fr-fr/huitre');

        for ($i = 0; $i < 2; ++$i) {
            $application = new Application(self::bootKernel());
            $command = $application->find('search-results:import');
            $commandTester = new CommandTester($command);
            $commandTester->execute(['--limit' => '-1', '--import-search' => true]);
            $commandTester->assertCommandIsSuccessful();

            $search = $this->getEntityManager()->getRepository(Search::class)->findOneBy(['keyword' => 'huitre']);
            $this->assertNotNull($search);
        }
    }

    public function testExtractTrends(): void
    {
        $application = new Application(self::bootKernel());
        $command = $application->find('search:extract-trends');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['keyword' => 'pizza avignon']);
        $commandTester->assertCommandIsSuccessful();

        /** @var Search */
        $search = $this->getEntityManager()->getRepository(Search::class)->findOrCreate('pizza avignon');
        $this->assertGreaterThan(1, $search->getSearchVolumeData()->getVolume());
        // dump($search->getSearchVolumeData()->getRelatedTopics());
        $this->assertContains('Avignon', $search->getSearchVolumeData()->getRelatedTopics()['/m/09hzc']);
    }
}
