<?php

namespace PiedWeb\Crawler;

use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Response;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\HrefLangExtractor;
use PiedWeb\Extractor\Indexable;
use PiedWeb\Extractor\InstagramUsernameExtractor;
use PiedWeb\Extractor\Link;
use PiedWeb\Extractor\LinksExtractor;
use PiedWeb\Extractor\RedirectionExtractor;
use PiedWeb\Extractor\RobotsTxtExtractor;
use PiedWeb\Extractor\TagExtractor;
use PiedWeb\Extractor\TextData;

class CrawlerUrl
{
    private static ?ExtendedClient $curlClient = null;

    public function __construct(
        protected Url $url,
        protected CrawlerConfig $config,
    ) {
        $this->url->harvester = $this;
        $this->harvest();
    }

    private function harvest(): void
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

    private function getCurlClient(): ExtendedClient
    {
        self::$curlClient ??= (new ExtendedClient())
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

        if ('' !== $this->config->userPassword) {
            self::$curlClient->setOpt(\CURLOPT_USERPWD, $this->config->userPassword);
        }

        return self::$curlClient;
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
                42 !== $this->getCurlClient()->getError() ? NetworkStatus::NETWORK_ERROR : NetworkStatus::TOO_BIG
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
        $this->url->setResponseTime((int) ((float) $response->getInfo('total_time') * 1000));
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
    private function defaultHarvesting(): void
    {
        if ([] === $this->config->toHarvest) {
            $this->harvestIndexable();
            $this->harvestLinks();
            $this->harvestTextData();
            $this->harvestTitle();
            $this->harvestH1();
            $this->harvestCanonical();
            $this->harvestHrefLang();
            $this->harvestSocialProfiles();

            return;
        }

        foreach ($this->config->toHarvest as $toHarvest) {
            $toHarvest = ucfirst($toHarvest);
            if (! method_exists($this, $harvestMethod = 'harvest'.$toHarvest)) {
                throw new \LogicException($harvestMethod.' doesn`t exist.');
            }

            $this->$harvestMethod();
        }
    }

    // /** @var Link[] */
    // private $links = [];
    private function isRedirection(): bool
    {
        $redirLink = (new RedirectionExtractor($this->url->getUrl(), $this->url->getParsedHeaders()))
            ->getRedirectionLink();

        if (! $redirLink instanceof Link) {
            return false;
        }

        // $this->links[] = $redirLink;
        $this->url->setRedirectUrl($redirLink->url);
        $this->url->setLinks([$redirLink]);
        $this->url->setIndexable(false);
        $this->url->setIndexableStatus(Indexable::NOT_INDEXABLE['redir']);

        return true;
    }

    private function harvestIndexable(): void
    {
        $indexable = new Indexable(
            $this->url->getUrl(),
            $this->config->isSameHostThanStartUrl($this->url->getUrl()->get())
                ? $this->config->getRobotsTxt()
                : (new RobotsTxtExtractor())->get($this->url->getUrl()),
            $this->url->getDomCrawler(),
            $this->url->getStatusCode(),
            $this->url->getHeaders()
        );
        $this->url->setIndexable($indexable->isIndexable());
        $this->url->setIndexableStatus($indexable->getIndexableStatus());
    }

    public function harvestLinks(string $selector = LinksExtractor::SELECT_ALL): void
    {
        $linksExtractor = new LinksExtractor(
            $this->url->getUrl(),
            $this->url->getDomCrawler(),
            $this->url->getHeaders(),
            $selector
        );
        $links = $linksExtractor->get();

        $this->url->setLinks($links);
        $this->url->setLinksTotal(\count($links));
        $this->url->setLinksSelf(\count($linksExtractor->get(Link::LINK_SELF)));
        $this->url->setLinksInternal(\count($linksExtractor->get(Link::LINK_INTERNAL)));
        $this->url->setLinksSub(\count($linksExtractor->get(Link::LINK_SUB)));
        $this->url->setLinksExternal(\count($linksExtractor->get(Link::LINK_EXTERNAL)));
        $this->url->setLinksDuplicate($linksExtractor->getNbrDuplicateLinks());
    }

    private function harvestSocialProfiles(): void
    {
        $extractor = (new InstagramUsernameExtractor($this->url->getHtml()));

        $this->url->instagramUsername = $extractor->extract();
        $this->url->youtubeChannel = $extractor->extractYoutubeChannel();
        $this->url->linkedin = $extractor->extractLinkedin();
    }

    private function harvestTextData(): void
    {
        $textData = new TextData($this->url->getHtml(), $this->url->getDomCrawler());
        $this->url->setWordCount($textData->getWordCount());
        $this->url->setTextRatio($textData->getRatioTxtCode());
        $this->url->setExpressions($textData->getTextAnalysis()->getExpressions(2));
        $this->url->setFlatContent($textData->getFlatContent());
    }

    private function harvestTitle(): void
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

    private function harvestHrefLang(): void
    {
        $this->url->setHrefLangList(
            (new HrefLangExtractor($this->url->getDomCrawler()))->getHrefLangList()
        );
    }

    private function harvestH1(): void
    {
        $this->url->setH1(
            (new TagExtractor($this->url->getDomCrawler()))
                ->getFirst('h1') ?? ''
        );
    }

    private function harvestCanonical(): void
    {
        $this->url->setCanonical(
            (new CanonicalExtractor($this->url->getUrl(), $this->url->getDomCrawler()))
                ->get()
        );
    }
}
