<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\ORM\Mapping as ORM;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: SearchRepository::class)]
#[UniqueEntity('keyword')]
class Search implements \Stringable
{
    public ?bool $disableExport = null;

    #[ORM\Id, ORM\Column(options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(unique: true)]
    private string $keyword;

    private string $lang = 'fr';

    private string $tld = 'fr';

    #[ORM\OneToOne(orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private SearchGoogleData $searchGoogleData;

    public function __construct()
    {
        $this->searchGoogleData = (new SearchGoogleData())->setSearch($this);
    }

    public function __toString(): string
    {
        return $this->getSearch();
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getTld(): string
    {
        return $this->tld;
    }

    public function getSearch(): string
    {
        return $this->keyword; // .' ('.$this->tld.'/'.$this->lang.')';
    }

    public function getHashId(): string
    {
        return self::getHashIdFrom($this->keyword, $this->tld, $this->lang);
    }

    public function getHashIdFrom(string $keyword, string $tld = 'fr', string $lang = 'fr'): string
    {
        $keyword = self::normalizeKeyword($keyword);

        return (new AsciiSlugger())->slug($keyword).'-'
            .substr(sha1($keyword.$tld.$lang), 0, 6);
    }

    public static function normalizeKeyword(string $keyword): string
    {
        return strtolower((new AsciiSlugger())->slug($keyword, ' '));
    }

    public function setKeyword(string $keyword): self
    {
        $this->keyword = self::normalizeKeyword($keyword);

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
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
}
