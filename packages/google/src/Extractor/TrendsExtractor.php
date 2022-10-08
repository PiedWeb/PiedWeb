<?php

namespace PiedWeb\Google\Extractor;

use Exception;

class TrendsExtractor
{
    /** @var array<int, int> */
    private array $volume = [];

    /**
     * @noRector
     *
     * @var array{}|array{'default': array{'timelineData': array<int,array{'time':int, 'value':int[]}>}}
     */
    public array $interestOverTime = [];

    /**
     * @noRector
     *
     * @var array{}|array{'default': array{'rankedList': array{0: array{'rankedKeyword': array{'topic': array{'mid':string, 'title': string, 'type': string}, 'value':int, 'link':string}[]}, 1: array{'rankedKeyword': array{'topic': array{'mid':string, 'title': string, 'type': string}, 'value':int, 'link':string}[]}}}}
     */
    public array $relatedTopics = [];

    /**
     * @noRector
     *
     * @var array{}|array{'default': array{'rankedList': array{0: array{'rankedKeyword': array{'query': string, 'value':int, 'link':string}[], 1: array{'rankedKeyword': array{'query': string, 'value':int, 'link':string}[]}}}}}
     */
    public array $relatedQueries = [];

    public function setRelatedTopics(mixed $relatedTopics): void
    {
        $this->relatedTopics = \Safe\json_decode(\Safe\json_encode($relatedTopics), true); // @phpstan-ignore-line
    }

    public function setInterestOverTime(mixed $interestOverTime): void
    {
        $this->interestOverTime = \Safe\json_decode(\Safe\json_encode($interestOverTime), true); // @phpstan-ignore-line
    }

    public function setRelatedQueries(mixed $relatedQueries): void
    {
        $this->relatedQueries = \Safe\json_decode(\Safe\json_encode($relatedQueries), true); // @phpstan-ignore-line
    }

    /** @return array<int, int> */
    public function getVolume(): array
    {
        if ([] !== $this->volume || [] === $this->interestOverTime) {
            return $this->volume;
        }

        $timelineData = $this->interestOverTime['default']['timelineData'];

        foreach ($timelineData as $data) {
            $this->volume[$data['time']] = $data['value'][0] ?? throw new Exception();
        }

        return $this->volume;
    }

    public function getVolumeAverage(): int
    {
        $volume = $this->getVolume();

        return (int) round(array_sum($volume) / \count($volume));
    }

    /** @return array{query:string, value: int, link:string}[] */
    public function getRelatedQueries(int $inProgression = 0): array
    {
        return [] === $this->relatedQueries ? []
            : $this->relatedQueries['default']['rankedList'][0 !== $inProgression ? 1 : 0]['rankedKeyword'];
    }

    /** @return array<string, int> */
    public function getRelatedQueriesSimplified(int $inProgression = 0): array
    {
        $return = [];
        $related = array_merge(
            2 === $inProgression ? $this->getRelatedQueries(0) : [],
            $this->getRelatedQueries(1 === $inProgression ? 1 : 0)
        );
        foreach ($related as $r) {
            $return[$r['query']] = $r['value'];
        }

        return $return;
    }

    /** @return array{'topic': array{'mid':string, 'title': string, 'type': string}, 'value':int, 'link':string}[] */
    public function getRelatedTopics(int $inProgression = 0): array
    {
        return [] === $this->relatedTopics ? []
            : $this->relatedTopics['default']['rankedList'][1 === $inProgression ? 1 : 0]['rankedKeyword'];
    }

    /** @return array{'mid':string, 'title': string, 'type': string, 'value':int}[] */
    public function getRelatedTopicsSimplified(int $inProgression = 0): array
    {
        $return = [];
        $related = array_merge(
            2 === $inProgression ? $this->getRelatedTopics(0) : [],
            $this->getRelatedTopics(1 === $inProgression ? 1 : 0)
        );
        foreach ($related as $r) {
            $return[$r['topic']['mid']] = array_merge($r['topic'], ['value' => $r['value']]);
        }

        return $return;
    }
}
