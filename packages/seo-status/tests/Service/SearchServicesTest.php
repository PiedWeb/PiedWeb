<?php

namespace PiedWeb\SeoStatus\Tests\Service;

use Doctrine\ORM\EntityManager;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchGoogleData;
use PiedWeb\SeoStatus\Entity\Search\SearchResult;
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

    private function getExtractor(): SearchExtractorService
    {
        /** @var string */
        $appDataDir = self::getContainer()->getParameter('app.dataDir');

        return new SearchExtractorService(
            new DataDirService('test', $appDataDir)
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

    public function testSearchExtractorService(): void
    {
        $json = $this->getJsonFromExtractorService();
        $json = json_decode($json);
        $this->assertSame('1', $json->version); // @phpstan-ignore-line
    }

    private function getImporter(): SearchImportJsonService
    {
        return new SearchImportJsonService($this->getEntityManager());
    }

    public function testSearchImporterJsonService(): void
    {
        $json = $this->getJsonFromExtractorService();

        $searchResults = $this->getImporter()->deserializeSearchResults($this->getSearch(), $json);

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
    }

    public function testImportSearch(): void
    {
        $search = $this->getSearch();
        $this->getEntityManager()->remove($search);
        $this->getEntityManager()->flush();

        $json = $this->getExtractor()->searchToJson($search);
        $importedSearch = $this->getImporter()->deserializeSearch($json);

        $this->assertSame($search->getSearchGoogleData()->getCpc(), $importedSearch->getSearchGoogleData()->getCpc());
        $this->assertSame($importedSearch, $importedSearch->getSearchGoogleData()->getSearch());
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
        $this->assertNull($searchRepository->findOneSearchToExtract());
    }

    public function testSimilar(): void
    {
        $search1 = (new Search())->setKeyword('Pizza avignon');
        $search2 = (new Search())->setKeyword('Pizzas avignon');
        $this->getEntityManager()->persist($search1);
        $this->getEntityManager()->persist($search2);
        $this->getEntityManager()->flush();

        $application = new Application(self::bootKernel());
        $command = $application->find('search:extract');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['keyword' => 'Pizza avignon']);
        $commandTester->assertCommandIsSuccessful();
        $commandTester->execute(['keyword' => 'Pizzas avignon']);
        $commandTester->assertCommandIsSuccessful();

        /** @var Search */
        $search1 = $this->getEntityManager()->getRepository(Search::class)->findOneBy(['keyword' => 'pizza avignon']);
        /** @var Search */
        $search2 = $this->getEntityManager()->getRepository(Search::class)->findOneBy(['keyword' => 'pizzas avignon']);
        $comparator = new SearchResultsComparator($this->getEntityManager());
        $similarityScolre = $comparator->getSimilarityScore($search1, $search2);
        $this->assertTrue((int) ($similarityScolre * 100) > 25);
        $areSimilar = $comparator->areSimilar($search1, $search2);
        $this->assertTrue($areSimilar);
    }
}
