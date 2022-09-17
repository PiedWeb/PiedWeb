<?php

namespace PiedWeb\SeoStatus\Entity\Search;

use Doctrine\Common\Collections\Collection;

trait SearchGoogleDataSearchResultsTrait
{
    abstract public function getSearchResultsList(): ?Collection;

    public function getLastSearchResultsFirstPixelPos(): ?int
    {
        $lastSearchResults = $this->getSearchResultsList()->first();
        if (false === $lastSearchResults) {
            return null;
        }

        $firsResult = $lastSearchResults->getResults()->first();

        return false === $firsResult ? null : (int) ($firsResult->getPixelPos() / 10);
    }

    public function getLastSearchResultsFirstHost(): ?string
    {
        $lastSearchResults = $this->getSearchResultsList()->first();
        if (false === $lastSearchResults) {
            return null;
        }

        $firsResult = $lastSearchResults->getResults()->first();

        return false === $firsResult ? null : $firsResult->getUrl()->getHost();
    }
}
