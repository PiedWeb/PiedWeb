<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use LogicException;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity]
class SearchGoogleData
{
    use SearchGoogleDataSearchResultsTrait;

    #[ORM\Id, ORM\Column(options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id;

    #[ORM\OneToOne(inversedBy: 'searchGoogleData')]
    #[ORM\JoinColumn(name: 'search_id', referencedColumnName: 'id')]
    #[Ignore]
    private ?Search $search = null;

    #[ORM\OneToOne(orphanRemoval: true, cascade: ['all'], mappedBy: 'searchGoogleData')]
    #[ORM\JoinColumn(nullable: false)]
    private SearchVolumeData $searchVolumeData;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $cpc = 0;

    public const INTENT = [
        0 => '',
        1 => 'navigation',
        2 => 'information',
        3 => 'transaction',
        4 => 'comparaison',
    ];

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $intent = 0;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lastExtractionAt = 0;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lastExtractionAskedAt = 0;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $firstExtractionAt = 0;

    public bool $firstExtractionWasRunned = false;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $nextExtractionFrom;

    /**
     * @noRector
     *
     * @var \Doctrine\Common\Collections\Collection<int, SearchResults>&iterable<SearchResults>
     */
    #[ORM\OneToMany(
        targetEntity: SearchResults::class,
        mappedBy: 'searchGoogleData',
        fetch: 'EXTRA_LAZY',
        orphanRemoval: true
    )]
    private $searchResultsList;

    // extra_lazy permits to use Collection#slice($offset, $length = null)

    #[ORM\OneToOne(
        targetEntity: SearchResults::class,
        cascade: ['persist'],
        fetch: 'EXTRA_LAZY',
    )]
    private ?SearchResults $lastSearchResults = null;

    /**
     * @var array<string, int>
     */
    public const ExtractionFrequency = [
        'none' => 0,
        'daily' => 1,
        'weekly' => 2,
        'monthly' => 3,
        'annually' => 4,
        'noneBecauseSameAs' => 5,
    ];

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $extractionFrequency = 4;

    /**
     * @var array<string, array<int, int>> where array<serpFeature, array<pixelPos, lastSeenAt>>
     */
    #[ORM\Column(type: 'json')]
    private array $serpFeatures = [];

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $relatedSearches = [];

    /**
     * @noRector
     *
     * @var \Doctrine\Common\Collections\Collection<int, SearchGoogleData>&iterable<SearchGoogleData> */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'similar')]
    #[ORM\JoinTable(name: 'similar_google_search_data')]
    private $similar;

    // 20% => 4 résultats sur les 20 premiers

    /**
     * @noRector
     *
     * @var \Doctrine\Common\Collections\Collection<int, SearchGoogleData>&iterable<SearchGoogleData> */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'comparable')]
    private $comparable;

    // 70%

    #[ORM\Column]
    private bool $comparableMain = true;

    public function __construct()
    {
        $this->nextExtractionFrom = (int) (new Datetime('now'))->format('ymdHi');
        $this->searchResultsList = new ArrayCollection();
        $this->similar = new \Doctrine\Common\Collections\ArrayCollection();
        $this->comparable = new \Doctrine\Common\Collections\ArrayCollection();
        $this->searchVolumeData = (new SearchVolumeData())->setSearchGoogleData($this);
    }

    /**
     * @param int $extractedAt format : ymdHi
     */
    public function updateExtractionMetadata(int $extractedAt): void
    {
        if (0 === $this->firstExtractionAt || $extractedAt < $this->firstExtractionAt) {
            $this->firstExtractionAt = $extractedAt;
        }

        if (0 === $this->lastExtractionAt || $extractedAt > $this->lastExtractionAt) {
            $this->lastExtractionAt = $extractedAt;
            $this->calculNextExtraction();
        }
    }

    public function calculNextExtraction(): void
    {
        if (0 === $this->lastExtractionAt) {
            $this->nextExtractionFrom = (int) (new Datetime('now'))->format('ymdHi');

            return;
        }

        $this->nextExtractionFrom = (int) \Safe\DateTime::createFromFormat(
            'ymdHi',
            (string) $this->lastExtractionAt
        )
            ->modify($this->getDateTimeModifyValueFromFrequency())
            ->format('ymdHi');
    }

    private function getDateTimeModifyValueFromFrequency(): string
    {
        $frequency = array_flip(self::ExtractionFrequency);

        if ('daily' === $frequency[$this->extractionFrequency]) {
            return '+1 day';
        }

        if ('weekly' === $frequency[$this->extractionFrequency]) {
            return '+1 week';
        }

        if ('monthly' === $frequency[$this->extractionFrequency]) {
            return '+1 month';
        }

        if ('annually' === $frequency[$this->extractionFrequency]) {
            return '+1 year';
        }

        return '+100 year';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCpc(): int
    {
        return $this->cpc;
    }

    public function setCpc(int $cpc): self
    {
        $this->cpc = $cpc;

        return $this;
    }

    public function getIntent(): int
    {
        return $this->intent;
    }

    public function setIntent(int $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    public function getLastExtractionAt(bool $datetime = false): int
    {
        return $this->lastExtractionAt;
    }

    public function setLastExtractionAt(int $lastExtractionAt): self
    {
        $this->lastExtractionAt = $lastExtractionAt;

        return $this;
    }

    public function getFirstExtractionAt(): int
    {
        return $this->firstExtractionAt;
    }

    public function setFirstExtractionAt(int $firstExtractionAt): self
    {
        if ($this->firstExtractionAt !== $firstExtractionAt) {
            $this->firstExtractionWasRunned = true;
        }

        $this->firstExtractionAt = $firstExtractionAt;

        return $this;
    }

    public function getNextExtractionFrom(): int
    {
        return $this->nextExtractionFrom;
    }

    public function setNextExtractionFrom(int $nextExtractionFrom): self
    {
        $this->nextExtractionFrom = $nextExtractionFrom;

        return $this;
    }

    public function getExtractionFrequency(): int
    {
        return $this->extractionFrequency;
    }

    public function setExtractionFrequency(int $extractionFrequency): self
    {
        $this->extractionFrequency = $extractionFrequency;

        return $this;
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function getSerpFeatures(): array
    {
        return $this->serpFeatures;
    }

    /**
     * @param array<string, array<int, int>> $serpFeatures
     */
    public function setSerpFeatures(array $serpFeatures): self
    {
        $this->serpFeatures = $serpFeatures;

        return $this;
    }

    /**
     * @param array<string, int> $serpFeatures
     */
    public function addSerpFeatures(array $serpFeatures, int $extractedAt): void
    {
        foreach ($serpFeatures as $serpFeature => $pixelPos) {
            $this->serpFeatures[$serpFeature] = [
                isset($this->serpFeatures[$serpFeature]) ? $this->serpFeatures[$serpFeature][0] : $pixelPos,
                $extractedAt, ];
        }
    }

    /**
     * @return string[]
     */
    public function getRelatedSearches(): array
    {
        return $this->relatedSearches;
    }

    /**
     * @param string[] $relatedSearches
     */
    public function setRelatedSearches(array $relatedSearches): self
    {
        $this->relatedSearches = $relatedSearches;

        return $this;
    }

    /**
     * @param string[] $searches
     */
    public function addRelatedSearches(array $searches): void
    {
        $this->relatedSearches = array_unique(array_merge($this->relatedSearches, $searches));
    }

    #[Ignore]
    public function getSearch(): Search
    {
        return $this->search ?? throw new LogicException();
    }

    public function setSearch(Search $search): self
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @return Collection<int, SearchResults>
     */
    public function getSearchResultsList(): Collection
    {
        return $this->searchResultsList;
    }

    public function addSearchResultsList(SearchResults $searchResultsList): self
    {
        if (! $this->searchResultsList->contains($searchResultsList)) {
            $this->searchResultsList->add($searchResultsList);
            $searchResultsList->setSearchGoogleData($this);
        }

        return $this;
    }

    public function removeSearchResultsList(SearchResults $searchResultsList): void
    {
        throw new LogicException();
    }

    public function getLastSearchResults(): ?SearchResults
    {
        return $this->lastSearchResults;
    }

    public function setLastSearchResults(?SearchResults $lastSearchResults): self
    {
        $this->lastSearchResults = $lastSearchResults;

        return $this;
    }

    public function isComparableMain(): bool
    {
        return $this->comparableMain;
    }

    public function setComparableMain(bool $comparableMain): self
    {
        $this->comparableMain = $comparableMain;

        return $this;
    }

    /**
     * @return Collection<int, SearchGoogleData>
     */
    public function getSimilar(): Collection
    {
        return $this->similar;
    }

    public function addSimilar(self $similar): self
    {
        if (! $this->similar->contains($similar)) {
            $this->similar->add($similar);
        }

        return $this;
    }

    public function removeSimilar(self $similar): self
    {
        $this->similar->removeElement($similar);

        return $this;
    }

    /**
     * @return Collection<int, SearchGoogleData>
     */
    public function getComparable(): Collection
    {
        return $this->comparable;
    }

    public function addComparable(self $comparable): self
    {
        if (! $this->comparable->contains($comparable)) {
            $this->comparable->add($comparable);
        }

        return $this;
    }

    public function removeComparable(self $comparable): self
    {
        $this->comparable->removeElement($comparable);

        return $this;
    }

    public function getLastExtractionAskedAt(): int
    {
        return $this->lastExtractionAskedAt;
    }

    public function setLastExtractionAskedAt(int $lastExtractionAskedAt): self
    {
        if (null !== $this->search) {
            $this->getSearch()->disableExport = true;
        }

        $this->lastExtractionAskedAt = $lastExtractionAskedAt;

        return $this;
    }

    public function getSearchVolumeData(): SearchVolumeData
    {
        return $this->searchVolumeData;
    }

    public function setSearchVolumeData(SearchVolumeData $searchVolumeData): self
    {
        $this->searchVolumeData = $searchVolumeData;

        return $this;
    }
}
