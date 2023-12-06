<?php

namespace PiedWeb\Crawler;

/**
 * Page Rank Calculator.
 */
abstract class AbstractPageRankCalculator
{
    protected float $dampingFactor = 0.85;

    protected int $maxIteration = 10000;

    protected ?int $pagesNbr = null;

    /**
     * @var array<int|string, float> where key is url (id) and value page rank
     */
    protected array $results = [];

    /**
     * @var array<int|string, array<int|string>> where key is destination (id) and value fromIdList
     */
    protected array $linksTo = [];

    /**
     * @var array<int|string, int> where key is from (id) and value count
     */
    protected array $nbrLinksFrom = [];

    protected function calcul(): void
    {
        for ($iteration = 0; $iteration < $this->maxIteration; ++$iteration) {
            $ids = array_keys($this->linksTo);
            foreach ($ids as $id) {
                $sumPR = 0;
                foreach ($this->getLinksTo($id) as $link) {
                    $sumPR += ($this->results[$link] ?? 0) / $this->getNbrLinksFrom($link);
                }

                $this->results[$id] = $this->dampingFactor * $sumPR + (1 - $this->dampingFactor) / $this->getPagesNbr();
            }
        }
    }

    protected function getPagesNbr(): int
    {
        return $this->pagesNbr ??= \count($this->linksTo);
    }

    /**
     * @return int[]|string[]
     */
    protected function getLinksTo(string|int $id): array
    {
        return $this->linksTo[$id];
    }

    protected function getNbrLinksFrom(int|string $id): int
    {
        return $this->nbrLinksFrom[$id];
    }
}
