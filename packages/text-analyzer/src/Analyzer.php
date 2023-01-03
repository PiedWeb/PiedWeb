<?php

namespace PiedWeb\TextAnalyzer;

use PiedWeb\Extractor\Helper;

/**
 * @see \PiedWeb\TextAnalyzer\Test\AnalyzerTest
 */
class Analyzer
{
    /**
     * @var array<string, int>
     */
    private array $expressions = [];

    private int $wordNumber = 0;

    public function __construct(
        private string $text,
        private readonly bool $onlyInSentence = false,
        private readonly int $expressionMaxWords = 5,
    ) {
        $this->text = CleanText::stripHtmlTags($this->text);
        $this->text = CleanText::fixEncoding($this->text);
        $this->text = CleanText::removeEmail($this->text);
        // $this->text = CleanText::removeDate($this->text);

        if ($this->onlyInSentence) {
            $this->text = CleanText::keepOnlySentence($this->text);
        }
    }

    protected function incrementWordNumber(int $value): void
    {
        $this->wordNumber += $value;
    }

    /**
     * @return string[]
     */
    private function getSentences(): array
    {
        if (! $this->onlyInSentence) {
            return explode(\chr(10), CleanText::fixEncoding($this->text));
        }

        $sentences = [];
        foreach (explode(\chr(10), $this->text) as $paragraph) {
            $sentences = array_merge($sentences, CleanText::getSentences($paragraph));
        }

        return $sentences;
    }

    public function exec(): Analysis
    {
        $sentences = $this->getSentences();

        foreach ($sentences as $sentence) {
            $wordsGroups = \Safe\preg_split('/(,|\.|\(|\[|!|\?|;|\{|:)/', $sentence);
            foreach ($wordsGroups as $wordsGroup) {
                $wordsGroup = CleanText::removePunctuation($wordsGroup);
                $this->extract($wordsGroup);
            }
        }

        $this->cleanExpressions();

        return new Analysis($this->expressions, $this->wordNumber);
    }

    private function cleanExpressions(): void
    {
        arsort($this->expressions);

        foreach (array_keys($this->expressions) as $expression) {
            $this->cleanSimilar($expression);
        }
    }

    private function cleanSimilar(string $expression): void
    {
        $similar = $this->findSimilar($expression);
        // can return énergies for énergie or énergie bio for énergie
        if ('' === $similar) {
            return;
        }

        // if we find énergie and énergie bio autant de fois
        // on supprime le plus court
        if ($this->expressions[$similar] === $this->expressions[$expression] && substr_count($expression, ' ') > 0) {
            unset($this->expressions[\strlen($similar) < \strlen($expression) ? $similar : $expression]);

            return;
        }

        // if (strlen($similar) < strlen($expression)
        //     && $this->expressions[$similar] <= $this->expressions[$expression]) {
        //     unset($this->expressions[$similar]);
        // }

        // return $this->cleanSimilar($expression);
    }

    private function findSimilar(string $expressionToCompare): string
    {
        foreach (array_keys($this->expressions) as $expression) {
            if ($expression === $expressionToCompare) {
                continue;
            }

            if (! str_contains($expression, $expressionToCompare)) {
                continue;
            }

            return $expression;
        }

        return '';
    }

    private function extract(string $words): void
    {
        $words = explode(' ', trim(mb_strtolower($words)));

        $wordsKey = array_keys($words);
        foreach ($wordsKey as $key) {
            for ($wordNumber = 1; $wordNumber <= $this->expressionMaxWords; ++$wordNumber) {
                $expression = '';
                for ($i = 0; $i < $wordNumber; ++$i) {
                    if (isset($words[$key + $i])) {
                        $expression .= ($i > 0 ? ' ' : '').$words[$key + $i];
                    }
                }

                $expression = $this->cleanExpr($expression, $wordNumber);

                if ('' === $expression
                    || (substr_count($expression, ' ') + 1 !== $wordNumber) // We avoid sur-pondération
                    || ! preg_match('#[a-z]#', $expression) // We avoid number or symbol only result
                ) {
                    if (1 === $wordNumber) {
                        $this->incrementWordNumber(-1);
                    }
                } else {
                    $plus = 1; // 1 + substr_count(CleanText::removeStopWords($expression), ' ');
                    $this->expressions[$expression] = ($this->expressions[$expression] ?? 0) + $plus;
                }
            }

            $this->incrementWordNumber(1);
        }
    }

    private function cleanExpr(string $expression, int $wordNumber): string
    {
        if (1 === \strlen($expression)) {
            return '';
        }

        if ($wordNumber <= 2) {
            $expression = trim(CleanText::removeStopWords(' '.$expression.' '));
        } else {
            $expression = CleanText::removeStopWordsAtExtremity($expression);
            $expression = CleanText::removeStopWordsAtExtremity($expression);
            if (! str_contains($expression, ' ')) {
                $expression = trim(CleanText::removeStopWords(' '.$expression.' '));
            }
        }

        // Last Clean
        $expression = trim(Helper::preg_replace_str('/\s+/', ' ', $expression));
        if ('' == htmlentities($expression)) { // Avoid �
            return '';
        }

        return $expression;
    }
}
