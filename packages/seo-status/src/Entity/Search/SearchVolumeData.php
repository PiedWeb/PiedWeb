<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Exception;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity]
class SearchVolumeData
{
    #[ORM\Id, ORM\Column(options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id;

    #[ORM\OneToOne(mappedBy: 'searchVolumeData', cascade: ['all'])]
    #[ORM\JoinColumn()]
    #[Ignore]
    private ?SearchGoogleData $searchGoogleData = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lastExtractionAt = 0;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lastExtractionAskedAt = 0;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $volume = 1;

    /** @var array<int, int> */
    #[ORM\Column(type: 'json')]
    private array $volumeOverTheTime = [];

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    private array $relatedSearches = [];

    #[ORM\ManyToOne(
        targetEntity: TrendsTopic::class,
        cascade: ['persist'],
        fetch: 'EXTRA_LAZY',
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?TrendsTopic $mainRelatedTopic = null;

    /** @var array<string, array{'mid': string, 'title': string, 'type': string, 'value': int}> where string is mid */
    #[ORM\Column(type: 'json')]
    private array $relatedTopics = [];

    /**
     * @noRector
     *
     * @var \Doctrine\Common\Collections\Collection<int, TrendsTopic>&iterable<TrendsTopic> */
    #[ORM\ManyToMany(
        targetEntity: TrendsTopic::class,
        mappedBy: 'relatedSearchVolumeDataList',
        fetch: 'EXTRA_LAZY',
        cascade: ['persist']
    )]
    #[ORM\JoinTable(name: 'related_topics_and_search')]
    private $relatedTopicsLinks;

    public function __construct()
    {
        $this->relatedTopicsLinks = new ArrayCollection();
    }

    #[Ignore]
    public function getSearchGoogleData(): SearchGoogleData
    {
        if (! $this->searchGoogleData instanceof SearchGoogleData) {
            throw new Exception();
        }

        return $this->searchGoogleData;
    }

    #[Ignore]
    public function setSearchGoogleData(SearchGoogleData $searchGoogleData): self
    {
        $this->searchGoogleData = $searchGoogleData;

        return $this;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function setVolume(int $volume): self
    {
        $this->volume = $volume;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLastExtractionAt(): int
    {
        return $this->lastExtractionAt;
    }

    public function setLastExtractionAt(int $lastExtractionAt): self
    {
        $this->lastExtractionAt = $lastExtractionAt;

        return $this;
    }

    /** @return array<int, int> */
    public function getVolumeOverTheTime(): array
    {
        return $this->volumeOverTheTime;
    }

    /**
     * @param array<int, int> $volumeOverTheTime
     */
    public function setVolumeOverTheTime(array $volumeOverTheTime): self
    {
        $this->volumeOverTheTime = $volumeOverTheTime;

        return $this;
    }

    /** @return array<string, int> */
    public function getRelatedSearches(): array
    {
        return $this->relatedSearches;
    }

    /**
     * @param array<string, int> $relatedSearches
     */
    public function setRelatedSearches(array $relatedSearches): self
    {
        $this->relatedSearches = $relatedSearches;

        return $this;
    }

    /**
     * @return array<string, array{'mid': string, 'title': string, 'type': string, 'value': int}> $relatedTopics
     */
    public function getRelatedTopics(): array
    {
        return $this->relatedTopics;
    }

    /**
     * @param array<string, array{'mid': string, 'title': string, 'type': string, 'value': int}> $relatedTopics
     */
    public function setRelatedTopics(array $relatedTopics): self
    {
        $this->relatedTopics = $relatedTopics;

        return $this;
    }

    public function getMainRelatedTopic(): ?TrendsTopic
    {
        return $this->mainRelatedTopic;
    }

    public function setMainRelatedTopic(?TrendsTopic $mainRelatedTopic): self
    {
        $this->mainRelatedTopic = $mainRelatedTopic;

        return $this;
    }

    /**
     * @return Collection<int, TrendsTopic>
     */
    public function getRelatedTopicsLinks(): Collection
    {
        return $this->relatedTopicsLinks;
    }

    public function addRelatedTopicsLink(TrendsTopic $relatedTopicsLink): self
    {
        if (! $this->relatedTopicsLinks->contains($relatedTopicsLink)) {
            $this->relatedTopicsLinks->add($relatedTopicsLink);
            $relatedTopicsLink->addRelatedSearchVolumeDataList($this);
        }

        return $this;
    }

    public function removeRelatedTopicsLink(TrendsTopic $relatedTopicsLink): self
    {
        if ($this->relatedTopicsLinks->removeElement($relatedTopicsLink)) {
            $relatedTopicsLink->removeRelatedSearchVolumeDataList($this);
        }

        return $this;
    }

    public function getLastExtractionAskedAt(): int
    {
        return $this->lastExtractionAskedAt;
    }

    public function setLastExtractionAskedAt(int $lastExtractionAskedAt): self
    {
        $this->lastExtractionAskedAt = $lastExtractionAskedAt;

        return $this;
    }

    #[Ignore]
    public function getSearch(): Search
    {
        return $this->getSearchGoogleData()->getSearch();
    }
}
