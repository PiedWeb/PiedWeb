<?php

namespace PiedWeb\TextAnalyzer;

final class MultiAnalyzer
{
    /**
     * @var Analysis[] contain the source texts
     */
    private array $text = [];

    public function __construct(
        private readonly bool $onlyInSentence = false,
        private readonly int $expressionMaxWords = 5
    ) {
    }

    public function addContent(string $text): Analysis
    {
        $text = (new Analyzer(
            $text,
            $this->onlyInSentence,
            $this->expressionMaxWords
        ))->exec();

        $this->text[] = $text;

        return $text;
    }

    public function exec(): Analysis
    {
        $mergedExpressions = [];

        foreach ($this->text as $text) {
            $expressions = $text->getExpressions();
            foreach ($expressions as $expression => $density) {
                $mergedExpressions[$expression] = (int) (($mergedExpressions[$expression] ?? 0)
                + $density)
                ;
            }
        }

        arsort($mergedExpressions);

        return new Analysis($mergedExpressions, $this->getWordNumber());
    }

    private function getWordNumber(): int
    {
        $wn = 0;
        foreach ($this->text as $text) {
            $wn += $text->getWordNumber();
        }

        return $wn;
    }
}
