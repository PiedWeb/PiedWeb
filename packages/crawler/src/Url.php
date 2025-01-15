<?php

namespace PiedWeb\Crawler;

use PiedWeb\Curl\Helper;
use PiedWeb\Extractor\Link;
use PiedWeb\Extractor\Url as UrlManipuler;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

final class Url
{
    private static int $autoIncrement = 1;

    private int $id = 0;

    private int $discovered = 0;

    private string $uri;

    private readonly UrlManipuler $url;

    private int $networkStatus = 0;

    private string $html = '';

    /** filepath */
    private string $source = '';

    private string $headers = '';

    private int $statusCode = 0;

    private float $pagerank = -1;

    /**
     * @var Link[]
     */
    private array $inboundLinksList = [];

    private int $inboundlinks = 0;

    private int $inboundlinksNofollow = 0;

    /**
     * @var Link[]
     */
    private array $links = [];

    private int $linksTotal = 0;

    private int $linksSelf = 0;

    private int $linksInternal = 0;

    private int $linksSub = 0;

    private int $linksExternal = 0;

    private int $linksDuplicate = 0;

    private ?bool $canBeCrawled = null;

    private bool $indexable = true;

    private int $indexableStatus = 0;

    private string $mimeType = '';

    private int $wordCount = 0;

    public string $instagramUsername = '';

    public string $youtubeChannel = '';

    /** @var array<string, string> */
    private array $flatContent = [];

    private int $textRatio = 0;

    private int $responseTime = 0;

    private int $size = 0;

    private string $title = '';

    private int $titlePixelWidth = 0;

    private string $metaDescription = '';

    private string $h1 = '';

    /**
     * @var array<string, float|int>
     */
    private array $expressions = [];

    private string $expressionsHash = '';

    /**
     * @var array<string, string>
     */
    private array $hrefLangList = [];

    private ?string $canonical = null;

    private string $redirectUrl = '';

    private \DateTimeInterface $updatedAt;

    /**
     * @var Link[]
     */
    private array $breadcrumb = [];

    private ?DomCrawler $domCrawler = null;

    /**
     * @var string[]
     */
    public const ARRAY_EXPORTED = [
        'expressions', 'breadcrumb',
    ];

    /**
     * @var string[]
     */
    public const EXPORTABLE = [
        'id',
        'discovered',
        'uri',
        'networkStatus',
        'source',
        'headers',
        'statusCode',
        'click',
        'pagerank',
        'inboundlinks',
        'inboundlinksNofollow',
        'linksTotal',
        'linksInternal',
        'linksSelf',
        'linksSub',
        'linksExternal',
        'canBeCrawled',
        'indexable',
        'indexableStatus',
        'mimeType',
        'wordCount',
        'textRatio',
        'responseTime',
        'size',
        'title',
        'titlePixelWidth',
        'metaDescription',
        'h1',
        'canonical',
        'redirectUrl',
        'expressions',
        'expressionsHash',
        'updatedAt',
    ];

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        $return = [];
        foreach (self::EXPORTABLE as $exportable) {
            $getter = 'get'.ucfirst($exportable);
            $value = $this->$getter();
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('y-m-d H:i:s');
            }

            if (! \is_string($value) && ! \is_int($value)
                && ! \is_float($value) && ! \is_bool($value) && null !== $value) {
                $getter = 'get'.ucfirst($exportable).'String';
                $value = $this->$getter();
            }

            \assert(\is_scalar($value));
            $return[$exportable] = (string) $value;
        }

        return $return;
    }

    public CrawlerUrl $harvester;

    public function __construct(string $url, private int $click = 0, int $id = 0)
    {
        $this->url = new UrlManipuler($url);
        if (($origin = $this->url->getOrigin()) === '') {
            throw new \LogicException('`$url` must contain origin (eg. : https://example.tld/my-page).');
        }

        $this->uri = substr($url, \strlen($origin));

        $this->id = 0 === $id ? $this->getId() : $id;

        $this->updatedAt = new \DateTime('now');
    }

    public function getId(): int
    {
        if (0 === $this->id) {
            $this->id = static::$autoIncrement;
            ++static::$autoIncrement;
        }

        return $this->id;
    }

    public function setDiscovered(int $discovered): static
    {
        $this->discovered = $discovered;

        return $this;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function setId(int|string $id): void
    {
        $this->id = (int) $id;
    }

    public function getDiscovered(): int
    {
        return $this->discovered;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function getClick(): int
    {
        return $this->click;
    }

    public function setClick(int $click): self
    {
        $this->click = $click;

        return $this;
    }

    public function getPagerank(): float
    {
        return $this->pagerank;
    }

    public function setPagerank(float|string|int $pagerank): void
    {
        $this->pagerank = (float) $pagerank;
    }

    public function getInboundlinks(): int
    {
        return $this->inboundlinks;
    }

    public function incrementInboundLinks(): void
    {
        ++$this->inboundlinks;
    }

    public function setInboundlinks(string|int $inboundlinks): void
    {
        $this->inboundlinks = (int) $inboundlinks;
    }

    public function getInboundlinksNofollow(): int
    {
        return $this->inboundlinksNofollow;
    }

    public function incrementInboundLinksNofollow(): void
    {
        ++$this->inboundlinksNofollow;
    }

    public function setInboundlinksNofollow(string|int $inboundlinksNofollow): void
    {
        $this->inboundlinksNofollow = (int) $inboundlinksNofollow;
    }

    public function getCanBeCrawled(): ?bool
    {
        return $this->canBeCrawled;
    }

    public function setCanBeCrawled(string|int|bool $canBeCrawled): bool
    {
        return $this->canBeCrawled = (bool) $canBeCrawled;
    }

    public function getIndexable(): bool
    {
        return $this->indexable;
    }

    public function setIndexable(string|int|bool $indexable): void
    {
        $this->indexable = (bool) $indexable;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /** @return Link[] */
    public function getLinks(): array
    {
        return $this->links;
    }

    /** @param Link[] $links */
    public function setLinks(array $links): void
    {
        $this->links = $links;
    }

    public function getDuplicateLinks(): int
    {
        return $this->linksDuplicate;
    }

    public function getLinksDuplicate(): int
    {
        return $this->linksDuplicate;
    }

    public function setLinksDuplicate(string|int $linksDuplicate): void
    {
        $this->linksDuplicate = (int) $linksDuplicate;
    }

    public function getLinksSelf(): int
    {
        return $this->linksSelf;
    }

    public function setLinksSelf(int $linksSelf): void
    {
        $this->linksSelf = $linksSelf;
    }

    public function getLinksInternal(): int
    {
        return $this->linksInternal;
    }

    public function setLinksInternal(int|string $linksInternal): void
    {
        $this->linksInternal = (int) $linksInternal;
    }

    public function getLinksSub(): int
    {
        return $this->linksSub;
    }

    public function setLinksSub(int|string $linksSub): void
    {
        $this->linksSub = (int) $linksSub;
    }

    public function getExternalLinks(): int
    {
        return $this->linksExternal;
    }

    public function getLinksExternal(): int
    {
        return $this->linksExternal;
    }

    public function setLinksExternal(int $linksExternal): void
    {
        $this->linksExternal = $linksExternal;
    }

    public function getWordCount(): int
    {
        return $this->wordCount;
    }

    public function setWordCount(int $wordCount): void
    {
        $this->wordCount = $wordCount;
    }

    public function getResponseTime(): int
    {
        return $this->responseTime;
    }

    public function setResponseTime(int $responseTime): void
    {
        $this->responseTime = $responseTime;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = substr($title, 0, 230);
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface|string $updatedAt): void
    {
        if (\is_string($updatedAt)) {
            $this->setUpdatedAtFromString($updatedAt);

            return;
        }

        $this->updatedAt = $updatedAt;
    }

    public function setUpdatedAtFromString(string $updatedAt): void
    {
        $this->updatedAt = \Safe\DateTime::createFromFormat('y-m-d H:i:s', $updatedAt);
    }

    public function getNetworkStatus(): int
    {
        return $this->networkStatus;
    }

    public function setNetworkStatus(int $networkStatus): void
    {
        $this->networkStatus = $networkStatus;
    }

    public function getHeaders(): string
    {
        return $this->headers;
    }

    public function setHeaders(string $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return array<int|string, string|string[]>
     */
    public function getParsedHeaders(): array
    {
        return Helper::httpParseHeaders($this->headers);
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        $this->source = realpath($source) ?: '';
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }

    public function getDomCrawler(): DomCrawler
    {
        return $this->domCrawler ??= new DomCrawler($this->html);
    }

    public function getIndexableStatus(): int
    {
        return $this->indexableStatus;
    }

    public function setIndexableStatus(int $indexableStatus): void
    {
        $this->indexableStatus = $indexableStatus;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int|string $statusCode): void
    {
        $this->statusCode = (int) $statusCode;
    }

    public function getUrl(): UrlManipuler
    {
        return $this->url;
    }

    public function getStringUrl(): string
    {
        return (string) $this->getUrl();
    }

    /**
     * @return Link[]
     */
    public function getInboundLinksList(): array
    {
        return $this->inboundLinksList;
    }

    /**
     * @param Link[] $inboundLinksList
     */
    public function setInboundLinksList(array $inboundLinksList): void
    {
        $this->inboundLinksList = $inboundLinksList;
    }

    public function getLinksTotal(): int
    {
        return $this->linksTotal;
    }

    public function setLinksTotal(int $linksTotal): void
    {
        $this->linksTotal = $linksTotal;
    }

    public function getTitlePixelWidth(): int
    {
        return $this->titlePixelWidth;
    }

    public function setTitlePixelWidth(int $titlePixelWidth): void
    {
        $this->titlePixelWidth = $titlePixelWidth;
    }

    /**
     * Get the value of textRatio.
     */
    public function getTextRatio(): int
    {
        return $this->textRatio;
    }

    public function setTextRatio(int $textRatio): void
    {
        $this->textRatio = $textRatio;
    }

    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(string $metaDescription): void
    {
        $this->metaDescription = substr($metaDescription, 0, 230);
    }

    public function getH1(): string
    {
        return $this->h1;
    }

    public function setH1(string $h1): void
    {
        $this->h1 = substr($h1, 0, 230);
    }

    /**
     * @return array<string, float|int>
     */
    public function getExpressions(): array
    {
        return $this->expressions;
    }

    public function getExpressionsString(): string
    {
        $return = '';
        foreach ($this->expressions as $kw => $v) {
            $return .= $kw.' :: '.$v.\chr(10);
        }

        return $return;
    }

    public function setExpressionsFromString(string $kws): void
    {
        $expressions = [];
        $kws = explode(\chr(10), trim($kws));
        foreach ($kws as $kw) {
            if ('' === $kw) {
                continue;
            }

            $kw = explode('::', $kw);
            $expressions[trim($kw[0])] = (int) trim($kw[1]);
        }

        $this->setExpressions($expressions);
    }

    /**
     * @param array<string, float|int>|string $expressions
     */
    public function setExpressions(array|string $expressions): void
    {
        if (\is_string($expressions)) {
            $this->setExpressionsFromString($expressions);

            return;
        }

        $this->expressions = $expressions;
        $this->expressionsHash = md5(implode('', \array_slice($expressions, 0, 10)));
    }

    public function getCanonical(): ?string
    {
        return $this->canonical;
    }

    public function setCanonical(?string $canonical): void
    {
        $this->canonical = $canonical;
    }

    /**
     * @return Link[]
     */
    public function getBreadcrumb(): array
    {
        return $this->breadcrumb;
    }

    /**
     * @param Link[] $breadcrumb
     */
    public function setBreadcrumb(array $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getBreadcrumbString(): string
    {
        $return = '';
        foreach ($this->breadcrumb as $link) {
            $return .= $link->toMarkdown().\chr(10);
        }

        return $return;
    }

    public function setBreadcrumbFromString(string $breadcrumb): void
    {
        // TODO
    }

    /** @return array<string, string> */
    public function getFlatContent(): array
    {
        return $this->flatContent;
    }

    public function getFlatContentString(): string
    {
        $toReturn = '';

        foreach (array_keys($this->flatContent) as $partContent) {
            $toReturn .= $partContent.\chr(10).\chr(10);
        }

        return $toReturn;
    }

    /** @param array<string, string> $flatContent */
    public function setFlatContent(array $flatContent): self
    {
        $this->flatContent = $flatContent;

        return $this;
    }

    /**
     * @return array<string,string>
     */
    public function getHrefLangList(): array
    {
        return $this->hrefLangList;
    }

    /**
     * @param array<string,string> $hrefLangList
     */
    public function setHrefLangList(array $hrefLangList): self
    {
        $this->hrefLangList = $hrefLangList;

        return $this;
    }

    /**
     * Get the value of expressionsHash.
     */
    public function getExpressionsHash(): string
    {
        return $this->expressionsHash;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->redirectUrl = $redirectUrl;
    }

    public function getInstagramUsername(): string
    {
        return $this->instagramUsername;
    }

    public function setInstagramUsername(string $instagramUsername): void
    {
        $this->instagramUsername = $instagramUsername;
    }

    public function getYoutubeChannel(): string
    {
        return $this->youtubeChannel;
    }

    public function setYoutubeChannel(string $youtubeChannel): void
    {
        $this->youtubeChannel = $youtubeChannel;
    }
}
