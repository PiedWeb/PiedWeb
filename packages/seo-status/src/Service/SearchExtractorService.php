<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\Google\Extractor\SERPExtractor;
use PiedWeb\Google\Extractor\SERPExtractorJsExtended;
use PiedWeb\Google\GoogleSERPManager;
use PiedWeb\GoogleSpreadsheetSeoScraper\RequestGoogleTrait;
use PiedWeb\SeoStatus\Entity\Proxy;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Repository\ProxyRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SearchExtractorService
{
    use RequestGoogleTrait;

    private ?Proxy $proxy = null;

    public function __construct(
        private DataDirService $dataDir,
        private ?EntityManagerInterface $entityManager = null,
        // private SearchImportJsonService $importer,
    ) {
    }

    protected function manageProxy(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        /** @var ProxyRepository */
        $proxyRepo = $this->entityManager->getRepository(Proxy::class);
        $this->proxy = $proxyRepo->findProxyReadyToUse();
        if (null === $this->proxy) {
            return;
        }

        // todo alert mail no more proxy

        $this->proxy->setLastUsedNow();
        $this->getClient()->setProxy($this->proxy->getProxy());
    }

    private function initSerpManager(Search $search): GoogleSERPManager
    {
        $Google = new GoogleSERPManager();
        $Google->q = $search->getKeyword();
        $Google->tld = $search->getTld();
        $Google->language = $search->getLang();
        $Google->setParameter('num', 100);

        return $Google;
    }

    public function extractGoogleResults(Search $search): string
    {
        $googleSerpManager = $this->initSerpManager($search);

        $googleSerpManager->cacheFolder = $this->dataDir.'tmp';

        $rawHtml = $googleSerpManager->getCache()
            ?? $googleSerpManager->setCache($this->requestGoogleWithCurl($googleSerpManager));

        $extractor = new SERPExtractorJsExtended($rawHtml);

        if ([] !== $extractor->getResults($organicOnly = false)) {
            $search->getSearchGoogleData()->updateExtractionMetadata();

            return $this->exportToJson($search, $extractor);
        }

        $googleSerpManager->deleteCache();
        if (null !== $this->proxy) {
            $this->proxy->setGoogleBlacklist(true);

            return $this->extractGoogleResults($search);
        }

        throw new \Exception('no google result : try new proxies, check the keyword `'.$search.'` or check selectors from Google libs.');
    }

    private function exportToJson(Search $search, SERPExtractor $extractor): string
    {
        $fileSystem = new Filesystem();
        $datetime = (new \DateTime('now'))->format('ymdHi');
        $json = $extractor->__toJson();
        $fileSystem->dumpFile($this->dataDir.$search->getHashId().'/'.$datetime.'.json', $json);
        $fileSystem->dumpFile($this->dataDir.$search->getHashId().'/lastResult.html', $extractor->html);

        return $json;
    }

    public function searchToJson(Search $search): string
    {
        $context = [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => fn ($object, $format, $context) => null,
            JsonEncode::OPTIONS => \JSON_PRETTY_PRINT,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['search', 'searchResultsList', 'previous', 'next', 'lastSearchResults', 'similar', 'comparable', 'comparableMain'],
        ];

        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $json = $serializer->serialize($search, 'json', $context);

        return $json;
    }

    public function exportSearchToJson(Search $search): void
    {
        $json = $this->searchToJson($search);
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile($this->dataDir.$search->getHashId().'/index.json', $json);
    }

    public function deleteSearch(Search $search): void
    {
        $fileSystem = new Filesystem();
        $fileSystem->remove($this->dataDir.$search->getHashId());
    }
}
