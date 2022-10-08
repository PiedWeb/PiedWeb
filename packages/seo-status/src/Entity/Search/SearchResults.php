<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use PiedWeb\SeoStatus\Entity\Url\Host;
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
        cascade: ['all']
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
     * @var array<string, int> where string is the serpFeature's name and int the pixel pos
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

    public function setExtractedAt(int|string $extractedAt): self
    {
        $this->extractedAt = (int) $extractedAt;

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
        $this->searchGoogleData->addSerpFeatures($serpFeatures, $this->extractedAt);

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
        // if (! $searchGoogleData instanceof SearchGoogleData) {
        // if (!is_array($searchGoogleData) || array_contain )
        // dd($searchGoogleData);

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

    public function retrieveSearchResultFor(Host $host, bool $returnOrganicFirst = true): ?SearchResult
    {
        foreach ($this->getResults() as $result) {
            if (true === $returnOrganicFirst && $result->isAds() && ! isset($paid)) {
                if ($result->getHost() === $host) {
                    $paid = $result;
                }

                continue;
            }

            if ($result->getHost() === $host) {
                return $result;
            }
        }

        return $paid ?? null;
    }

    public function calculateMovement(): self
    {
        if (null === $this->previous) {
            return $this;
        }

        $results = $this->getOrganicResultsByHost();
        $previousResults = $this->previous->getOrganicResultsByHost();
        foreach ($results as $host => $searchResult) {
            if (isset($previousResults[$host])) {
                $searchResult
                    ->setMovement($previousResults[$host]->getPos() - $searchResult->getPos())
                    ->setMovementNew(false);
                unset($previousResults[$host]);
            } else {
                $searchResult
                    ->setMovement($this->previous->getLastSearchResult()->getPos() + 1 - $searchResult->getPos())
                    ->setMovementNew(true);
            }
        }

        // in previousResults we have the lost
        return $this;
    }

    public function getLastSearchResult(): SearchResult
    {
        return $this->results->last() ?: throw new LogicException();
    }

    /**
     * Parse each searchResult keeping only firstResult for a host.
     *
     * @return array<string, SearchResult>
     */
    public function getOrganicResultsByHost(): array
    {
        $return = [];
        foreach ($this->results as $result) {
            if (! $result->isAds()) {
                $return[(string) $result->getHost()] ??= $result;
            }
        }

        return $return;
    }

    public function getFirst(bool $paid = false): SearchResult
    {
        foreach ($this->results as $result) {
            if (false === $paid && $result->isAds()) {
                continue;
            } else {
                return $result;
            }
        }

        throw new LogicException();
    }
}
