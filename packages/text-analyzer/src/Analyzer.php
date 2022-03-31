<?php

namespace PiedWeb\TextAnalyzer;

use PiedWeb\Extractor\Helper;

class Analyzer
{
    /**
     * @var array<string, int>
     */
    private array $expressions = [];

    private int $wordNumber = 0;

    public function __construct(
        private string $text,
        private bool $onlyInSentence = false,
        private int $expressionMaxWords = 5,
    ) {
        $this->text = CleanText::stripHtmlTags($this->text);
        $this->text = CleanText::fixEncoding($this->text);
        $this->text = CleanText::removeDate($this->text);

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
            return explode(\chr(10), trim($this->text));
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
            $this->extract($sentence);
        }

        arsort($this->expressions);

        return new Analysis($this->expressions, $this->wordNumber);
    }

    private function extract(string $sentence): void
    {
        $sentence = CleanText::removePunctuation($sentence);

        $words = explode(' ', trim(strtolower($sentence)));

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
                    || ((substr_count($expression, ' ') + 1) != $wordNumber) // We avoid sur-pondération
                    || ! preg_match('/[a-z]/', $expression) // We avoid number or symbol only result
                ) {
                    if (1 === $wordNumber) {
                        $this->incrementWordNumber(-1);
                    }
                } else {
                    $plus = 1 + substr_count(CleanText::removeStopWords($expression), ' ');
                    $this->expressions[$expression] = ($this->expressions[$expression] ?? 0) + $plus;
                }
            }

            $this->incrementWordNumber(1);
        }
    }

    private function cleanExpr(string $expression, int $wordNumber): string
    {
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
            $expression = '';
        }

        return $expression;
    }
}
