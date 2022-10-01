<?php

namespace PiedWeb\SeoStatus\Service;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Google\Extractor\SERPExtractor;
use PiedWeb\Google\Extractor\SERPExtractorJsExtended;
use PiedWeb\Google\GoogleException;
use PiedWeb\Google\GoogleRequester;
use PiedWeb\Google\GoogleSERPManager;
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
    private ?Proxy $proxy = null;

    public function __construct(
        private DataDirService $dataDir,
        private ?EntityManagerInterface $entityManager = null,
        // private SearchImportJsonService $importer,
    ) {
    }

    public function manageProxy(ExtendedClient $curlClient): void
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
        $curlClient->setProxy($this->proxy->getProxy());
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
            ?? $googleSerpManager->setCache((new GoogleRequester())->requestGoogleWithCurl($googleSerpManager, [$this, 'manageProxy']));

        $extractor = new SERPExtractorJsExtended($rawHtml, $googleSerpManager->getExtractedAt());

        if ([] !== $extractor->getResults($organicOnly = false)) {
            $search->disableExport = true;
            $search->getSearchGoogleData()->updateExtractionMetadata($googleSerpManager->getExtractedAt()); // useful ?!

            return $this->exportToJson($search, $extractor);
        }

        $googleSerpManager->deleteCache();
        if (null !== $this->proxy) {
            $this->proxy->setGoogleBlacklist(true);

            return $this->extractGoogleResults($search);
        }

        \Safe\file_put_contents('/tmp/debug.html', $rawHtml);

        throw new GoogleException('no google result from `/tmp/debug.html` : try new proxies, check the keyword `'.$search.'` or check selectors from Google libs.');
    }

    private function exportToJson(Search $search, SERPExtractor $extractor): string
    {
        $fileSystem = new Filesystem();
        $json = $extractor->__toJson();
        $lastExtractionAskedAt = $search->getSearchGoogleData()->getLastExtractionAskedAt();
        $searchExportDir = $this->dataDir->getSearchDir($search);
        $fileSystem->dumpFile($searchExportDir.$lastExtractionAskedAt.'.json', $json);
        $fileSystem->dumpFile($searchExportDir.'lastResult.html', $extractor->html);

        return $json;
    }

    public function searchToJson(Search $search): string
    {
        $context = [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => fn ($object, $format, $context) => null,
            JsonEncode::OPTIONS => \JSON_PRETTY_PRINT,
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['search', 'searchResultsList', 'previous', 'next', 'lastSearchResults', 'similar', 'comparable', 'comparableMain', 'disableExport', 'lastExtractionAskedAt', 'hashId'],
        ];

        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $json = $serializer->serialize($search, 'json', $context);

        return $json;
    }

    public function exportSearchToJson(Search $search): void
    {
        $json = $this->searchToJson($search);
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile($this->dataDir->getSearchDir($search).'index.json', $json);
    }

    public function deleteSearch(Search $search): void
    {
        $fileSystem = new Filesystem();
        $fileSystem->remove($this->dataDir->getSearchDir($search));
    }
}
