<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PiedWeb\SeoStatus\Repository\SearchResultsRepository;

#[ORM\Entity(repositoryClass: SearchResultsRepository::class)]
class SearchResults
{
    #[ORM\Id, ORM\Column(options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: SearchGoogleData::class, inversedBy: 'searchResultsList')]
    #[ORM\JoinColumn(name: 'search_google_data_id', referencedColumnName: 'id', nullable: false)]
    private SearchGoogleData $searchGoogleData;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $extractedAt = 0;

    /**
     * @noRector
     *
     * @var \Doctrine\Common\Collections\Collection<int, SearchResult>&iterable<SearchResult>
     * */
    #[ORM\OneToMany(
        targetEntity: SearchResult::class,
        mappedBy: 'searchResults',
        orphanRemoval: true,
        cascade: ['persist', 'remove']
    )]
    private $results;

    #[ORM\OneToOne(targetEntity: self::class, inversedBy: 'next', cascade: ['persist'])]
    private ?self $previous = null;

    #[ORM\OneToOne(targetEntity: self::class, inversedBy: 'previous', cascade: ['persist'])]
    private ?self $next = null;

    #[ORM\Column()]
    private bool $isLast = true;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $resultStat = 0;

    /**
     * @var array<string, int>
     */
    #[ORM\Column(type: 'json')]
    private array $serpFeatures = [];

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $relatedSearches = [];

    public function __construct()
    {
        $this->results = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, SearchResult>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(SearchResult $result): self
    {
        if (! $this->results->contains($result)) {
            $this->results->add($result);
            $result->setSearchResults($this);
        }

        return $this;
    }

    public function getExtractedAt(): int
    {
        return $this->extractedAt;
    }

    public function setExtractedAt(int $extractedAt): self
    {
        $this->extractedAt = $extractedAt;

        return $this;
    }

    public function getResultStat(): int
    {
        return $this->resultStat;
    }

    public function setResultStat(int $resultStat): self
    {
        $this->resultStat = $resultStat;

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getSerpFeatures(): array
    {
        return $this->serpFeatures;
    }

    /**
     * @param array<string, int> $serpFeatures
     */
    public function setSerpFeatures(array $serpFeatures): self
    {
        $this->serpFeatures = $serpFeatures;
        $this->searchGoogleData->addSerpFeatures(array_keys($serpFeatures));

        return $this;
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
        $this->searchGoogleData->addRelatedSearches($relatedSearches);

        return $this;
    }

    public function getSearchGoogleData(): SearchGoogleData
    {
        return $this->searchGoogleData;
    }

    public function setSearchGoogleData(SearchGoogleData $searchGoogleData): self
    {
        $this->searchGoogleData = $searchGoogleData;

        return $this;
    }

    public function isIsLast(): bool
    {
        return $this->isLast;
    }

    public function setIsLast(bool $isLast): self
    {
        $this->isLast = $isLast;

        return $this;
    }

    public function removeResult(SearchResult $result): self
    {
        return $this;
    }

    public function getPrevious(): ?self
    {
        return $this->previous;
    }

    public function setPrevious(?self $previous): self
    {
        $this->previous = $previous;
        if (null !== $previous) {
            $previous->setNext($this);
        }

        return $this;
    }

    public function getNext(): ?self
    {
        return $this->next;
    }

    public function setNext(?self $next): self
    {
        $this->isLast = false;
        $this->next = $next;

        return $this;
    }
}
