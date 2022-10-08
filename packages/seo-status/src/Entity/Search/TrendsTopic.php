<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinTable;
use PiedWeb\SeoStatus\Repository\TrendsTopicRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TrendsTopicRepository::class)]
#[UniqueEntity('mid')]
class TrendsTopic
{
    #[ORM\Id, ORM\Column(options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(unique: true)]
    private string $mid;

    #[ORM\Column()]
    private string $title;

    #[ORM\Column()]
    private string $type;

    /** @var array<string, array{'title': string, 'type': string, 'value': int}> where string is mid */
    #[ORM\Column(type: 'json')]
    private array $relatedTopics = [];

    /**
     * @noRector
     *
     * @var \Doctrine\Common\Collections\Collection<int, SearchVolumeData>&iterable<SearchVolumeData> */
    #[ORM\ManyToMany(
        targetEntity: SearchVolumeData::class,
        inversedBy: 'relatedTopicsLinks',
        fetch: 'EXTRA_LAZY',
        cascade: ['persist']
    )]
    #[ORM\JoinTable(name: 'related_topics_and_search')]
    private $relatedSearchVolumeDataList;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $volume = 1;

    /** @var array<int, int> */
    #[ORM\Column(type: 'json')]
    private array $volumeOverTheTime = [];

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lastExtractionAt = 0;

    public function __construct()
    {
        $this->relatedSearchVolumeDataList = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMid(): string
    {
        return $this->mid;
    }

    public function setMid(string $mid): self
    {
        $this->mid = $mid;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /** @return array<string, array{'title': string, 'type': string, 'value': int}> where string is mid */
    public function getRelatedTopics(): array
    {
        return $this->relatedTopics;
    }

    /**
     * @param array<string, array{'title': string, 'type': string, 'value': int}> $relatedTopics where key-string is mid
     */
    public function setRelatedTopics(array $relatedTopics): self
    {
        $this->relatedTopics = $relatedTopics;

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

    public function getLastExtractionAt(): int
    {
        return $this->lastExtractionAt;
    }

    public function setLastExtractionAt(int $lastExtractionAt): self
    {
        $this->lastExtractionAt = $lastExtractionAt;

        return $this;
    }

    /**
     * @return Collection<int, SearchVolumeData>
     */
    public function getRelatedSearchVolumeDataList(): Collection
    {
        return $this->relatedSearchVolumeDataList;
    }

    public function addRelatedSearchVolumeDataList(SearchVolumeData $relatedSearchVolumeDataList): self
    {
        if (! $this->relatedSearchVolumeDataList->contains($relatedSearchVolumeDataList)) {
            $this->relatedSearchVolumeDataList->add($relatedSearchVolumeDataList);
        }

        return $this;
    }

    public function removeRelatedSearchVolumeDataList(SearchVolumeData $relatedSearchVolumeDataList): self
    {
        $this->relatedSearchVolumeDataList->removeElement($relatedSearchVolumeDataList);

        return $this;
    }

    /** @return array<int, int> */
    public function getVolumeOverTheTime(): array
    {
        return $this->volumeOverTheTime;
    }

    /** @param array<int, int> $volumeOverTheTime */
    public function setVolumeOverTheTime(array $volumeOverTheTime): self
    {
        $this->volumeOverTheTime = $volumeOverTheTime;

        return $this;
    }
}
