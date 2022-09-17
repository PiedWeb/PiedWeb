<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\ORM\Mapping as ORM;
use PiedWeb\SeoStatus\Entity\Url\Host;
use PiedWeb\SeoStatus\Entity\Url\Uri;
use PiedWeb\SeoStatus\Entity\Url\Url;

#[ORM\Entity]
class SearchResult
{
    #[ORM\Id, ORM\Column(options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $pos;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $pixelPos = 0;

    #[ORM\ManyToOne(targetEntity: Url::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private Url $url;

    #[ORM\ManyToOne(targetEntity: Host::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private Host $host;

    #[ORM\ManyToOne(targetEntity: Uri::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private Uri $uri;

    #[ORM\Column]
    private bool $ads = false;

    #[ORM\ManyToOne(targetEntity: SearchResults::class, inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    private SearchResults $searchResults;

    public function getId(): int
    {
        return $this->id;
    }

    public function getPos(): int
    {
        return $this->pos;
    }

    public function setPos(int $pos): self
    {
        $this->pos = $pos;

        return $this;
    }

    public function getPixelPos(): int
    {
        return $this->pixelPos;
    }

    public function setPixelPos(int $pixelPos): self
    {
        $this->pixelPos = $pixelPos;

        return $this;
    }

    public function isAds(): bool
    {
        return $this->ads;
    }

    public function setAds(bool $ads): self
    {
        $this->ads = $ads;

        return $this;
    }

    public function getUrl(): Url
    {
        return $this->url;
    }

    public function setUrl(Url $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getSearchResults(): SearchResults
    {
        return $this->searchResults;
    }

    public function setSearchResults(SearchResults $searchResults): self
    {
        $this->searchResults = $searchResults;

        return $this;
    }

    public function getHost(): Host
    {
        return $this->host;
    }

    public function setHost(Host $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    public function setUri(Uri $uri): self
    {
        $this->uri = $uri;

        return $this;
    }
}
