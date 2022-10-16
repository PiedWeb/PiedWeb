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

    #[ORM\Column]
    private int $movement = 0;

    #[ORM\Column(nullable: true)]
    private ?bool $movementNew = null;

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

    public function getMovement(): int
    {
        return $this->movement;
    }

    public function setMovement(int $movement): self
    {
        $this->movement = $movement;

        return $this;
    }

    public function getMovementNew(): ?bool
    {
        return $this->movementNew;
    }

    public function setMovementNew(bool $movementNew): self
    {
        $this->movementNew = $movementNew;

        return $this;
    }

    public function getMovementStr(): string
    {
        return (0 === $this->movement || null === $this->movementNew) ? ''
                : ($this->movementNew ? 'new' : ($this->movement > 0 ? '+' : '').$this->movement);
    }

    public function isMovementNew(): ?bool
    {
        return $this->movementNew;
    }

    /**
     From 0 to
     1: 200
     */
    public function getPixelPosRank(): int
    {
        if ($this->getPixelPos() < 1) {
            return 0;
        }

        if ($this->getPixelPos() <= 450 && 1 === $this->getPos()) {
            return 1;
        }

        $medianSize = $this->getSearchResults()->getMedianPixelSizeForOneResult();
        $pixelPos = 450;
        for ($i = 2; $i <= 150; ++$i) {
            if ($this->getPixelPos() <= $pixelPos + $medianSize) {
                return $i;
            }

            $pixelPos += $medianSize;
        }

        return $i;
    }

    /**
      6+: 0,001
      5: 0,05
      4: 0,1
      3: 0,15
      2: 0,3
      1: 0,399
     */
    private function calculateVisibilityScore(int $pos): int
    {
        $volume = $this->searchResults->getSearchGoogleData()->getSearchVolumeData()->getVolume();

        $posFactor = [
            5 => 0.05,
            4 => 0.1,
            3 => 0.15,
            2 => 0.3,
            1 => 0.399,
        ];

        if ($pos < 1) {
            return 0;
        }

        if ($pos >= 6) {
            $posFactor = \floatval('0.001'.sprintf('%0'.(\strlen((string) $pos) * 2 - 1).'d', $pos));

            return (int) ceil($posFactor * $volume);
        }

        return (int) ceil($pos * $posFactor[$pos] * $volume);
    }

    public function getVisibilityScore(): int
    {
        $searchVolumeData = $this->searchResults->getSearchGoogleData()->getSearchVolumeData();

        if (0 === $this->getPixelPos()) {
            return $this->getOrganicVisibilityScore();
        }

        return $this->calculateVisibilityScore($this->getPixelPosRank());
    }

    public function getOrganicVisibilityScore(): int
    {
        return $this->calculateVisibilityScore($this->pos);
    }
}
