<?php

namespace PiedWeb\Crawler;

use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Response;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\HrefLangExtractor;
use PiedWeb\Extractor\Indexable;
use PiedWeb\Extractor\Link;
use PiedWeb\Extractor\LinksExtractor;
use PiedWeb\Extractor\RedirectionExtractor;
use PiedWeb\Extractor\RobotsTxtExtractor;
use PiedWeb\Extractor\TagExtractor;
use PiedWeb\Extractor\TextData;

class CrawlerUrl
{
    /** @var Link[] */
    protected $links = [];

    protected static ?ExtendedClient $curlClient = null;

    public function __construct(
        protected Url $url,
        protected CrawlerConfig $config
    ) {
        $this->harvest();
    }

    protected function harvest(): void
    {
        $this->request();
        if (0 !== $this->url->getNetworkStatus()) {
            return;
        }

        if ($this->isRedirection()) {
            return;
        }

        $this->defaultHarvesting();
    }

    protected function getCurlClient(): ExtendedClient
    {
        return self::$curlClient ??= (new ExtendedClient())
            ->setDefaultGetOptions()
            ->setDefaultSpeedOptions()
            ->setMaximumResponseSize(1_000_000) // 1Mo
            ->fakeBrowserHeader()
            ->setUserAgent($this->config->userAgent)
            ->setOpt(\CURLOPT_MAXREDIRS, 0)
            ->setOpt(\CURLOPT_FOLLOWLOCATION, false)
            // ->setOpt(\CURLOPT_COOKIE, false)
            ->setOpt(\CURLOPT_CONNECTTIMEOUT, 20)
            ->setOpt(\CURLOPT_TIMEOUT, 80);
    }

    protected function request(): void
    {
        if ($this->config->executeJs) {
            throw new \Exception('Not yet implemented');
        }

        $request = $this->getCurlClient()
            ->request($this->config->getBase().$this->url->getUri());

        if (! $request) {
            $this->url->setNetworkStatus(
                42 != $this->getCurlClient()->getError() ? NetworkStatus::NETWORK_ERROR : NetworkStatus::TOO_BIG
            );
            $responseToCache = 'curl_error_code:'.$this->getCurlClient()->getError();
        }

        $this->setUrlDataFromResponse($this->getCurlClient()->getResponse());

        $this->config->getRecorder()->cache(
            $responseToCache ?? $this->getCurlClient()->getResponse(),
            $this->url
        );
        $this->url->setSource($this->config->getRecorder()->getCacheFilePath($this->url));
    }

    protected function setUrlDataFromResponse(Response $response): void
    {
        $this->url->setHeaders($response->getRawHeaders());
        $this->url->setStatusCode($response->getStatusCode());
        $this->url->setMimeType($response->getMimeType());
        $this->url->setResponseTime((int) $response->getInfo('total_time'));
        $this->url->setSize((int) $response->getInfo('size_download'));

        if ('text/html' !== $response->getMimeType()) {
            $this->url->setNetworkStatus(NetworkStatus::NOT_HTML);
        } elseif (200 === $response->getStatusCode()) {
            $this->url->setHtml($response->getBody());
        }
    }

    /**
     * permit to easily extend and change what is harvested, for example adding :
     * $this->harvestBreadcrumb();
     * $this->url->setKws(','.implode(',', array_keys($this->getHarvester()->getKws())).','); // Slow ~20%
     * $this->url->setRatioTextCode($this->getHarvester()->getRatioTxtCode()); // Slow ~30%
     * $this->url->setH1($this->getHarvester()->getUniqueTag('h1') ?? '');.
     */
    protected function defaultHarvesting(): void
    {
        foreach ($this->config->toHarvest as $toHarvest) {
            $toHarvest = ucfirst($toHarvest);
            if (! method_exists($this, $harvestMethod = 'harvest'.$toHarvest)) {
                throw new \LogicException($harvestMethod.' doesn`t exist.');
            }

            $this->$harvestMethod();
        }
    }

    protected function isRedirection(): bool
    {
        $redirLink = (new RedirectionExtractor($this->url->getUrl(), $this->url->getParsedHeaders()))
            ->getRedirectionLink();

        if (! $redirLink instanceof \PiedWeb\Extractor\Link) {
            return false;
        }

        $this->links[] = $redirLink;

        $this->url->setLinks([$redirLink]);
        $this->url->setIndexable(false);
        $this->url->setIndexableStatus(Indexable::NOT_INDEXABLE['redir']);

        return true;
    }

    protected function harvestIndexable(): void
    {
        $indexable = new Indexable(
            $this->url->getUrl(),
            (new RobotsTxtExtractor())->get($this->url->getUrl()),
            $this->url->getDomCrawler(),
            $this->url->getStatusCode(),
            $this->url->getHeaders()
        );
        $this->url->setIndexable($indexable->isIndexable());
        $this->url->setIndexableStatus($indexable->getIndexableStatus());
    }

    protected function harvestLinks(): void
    {
        $linksExtractor = new LinksExtractor(
            $this->url->getUrl(),
            $this->url->getDomCrawler(),
            $this->url->getHeaders(),
            LinksExtractor::SELECT_ALL
        );
        $links = $linksExtractor->get();
        foreach ($links as $link) {
            $this->links[] = $link;
        }

        $this->url->setLinks($links);
        $this->url->setLinksTotal(\count($links));
        $this->url->setLinksSelf(\count($linksExtractor->get(Link::LINK_SELF)));
        $this->url->setLinksInternal(\count($linksExtractor->get(Link::LINK_INTERNAL)));
        $this->url->setLinksSub(\count($linksExtractor->get(Link::LINK_SUB)));
        $this->url->setLinksExternal(\count($linksExtractor->get(Link::LINK_EXTERNAL)));
        $this->url->setLinksDuplicate($linksExtractor->getNbrDuplicateLinks());
    }

    protected function harvestTextData(): void
    {
        $textData = new TextData($this->url->getHtml(), $this->url->getDomCrawler());
        $this->url->setWordCount($textData->getWordCount());
        $this->url->setTextRatio($textData->getRatioTxtCode());
        $this->url->setExpressions($textData->getTextAnalysis()->getExpressions(2));
        $this->url->setFlatContent($textData->getFlatContent());
    }

    protected function harvestTitle(): void
    {
        $this->url->setTitle(
            (new TagExtractor($this->url->getDomCrawler()))
                ->getFirst('head title') ?? ''
        );

        $nodeMetaDesc = $this->url->getDomCrawler()->filterXPath('//meta[@name="description"]');
        if ($nodeMetaDesc->count() > 0) {
            $this->url->setMetaDescription(
                $nodeMetaDesc->attr('content') ?? ''
            );
        }
    }

    protected function harvestHrefLang(): void
    {
        $this->url->setHrefLangList(
            (new HrefLangExtractor($this->url->getDomCrawler()))->getHrefLangList()
        );
    }

    protected function harvestH1(): void
    {
        $this->url->setH1(
            (new TagExtractor($this->url->getDomCrawler()))
                ->getFirst('h1') ?? ''
        );
    }

    protected function harvestCanonical(): void
    {
        $this->url->setCanonical(
            (new CanonicalExtractor($this->url->getUrl(), $this->url->getDomCrawler()))
                ->get()
        );
    }
}
