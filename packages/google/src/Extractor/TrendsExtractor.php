<?php

namespace PiedWeb\Google\Extractor;

class TrendsExtractor
{
    /** @var array<int, int> */
    private array $interest = [];

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

    public function setRelatedTopics(string $relatedTopics): void
    {
        $this->relatedTopics = '' === $relatedTopics ? [] // @phpstan-ignore-line
            : \Safe\json_decode($relatedTopics, true);
    }

    public function setInterestOverTime(string $interestOverTime): void
    {
        $this->interestOverTime = '' === $interestOverTime ? [] // @phpstan-ignore-line
            : \Safe\json_decode($interestOverTime, true);
    }

    public function setRelatedQueries(string $relatedQueries): void
    {
        $this->relatedQueries = '' === $relatedQueries ? [] // @phpstan-ignore-line
            : \Safe\json_decode($relatedQueries, true);
    }

    /** @return array<int, int> */
    public function getInterest(): array
    {
        if ([] !== $this->interest || [] === $this->interestOverTime) {
            return $this->interest;
        }

        $timelineData = $this->interestOverTime['default']['timelineData'];

        foreach ($timelineData as $data) {
            $this->interest[$data['time']] = $data['value'][0] ?? throw new \Exception();
        }

        return $this->interest;
    }

    public function getInterestAverage(): int
    {
        $interest = $this->getinterest();

        return 0 === \count($interest) ? 1
            : (int) round(array_sum($interest) / \count($interest));
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
