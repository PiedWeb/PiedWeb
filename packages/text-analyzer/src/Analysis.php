<?php

namespace PiedWeb\TextAnalyzer;

final class Analysis
{
    /**
     * @param array<string, int> $expressions
     */
    public function __construct(
        private array $expressions,
        private int $wordNumber = 0,
    ) {
    }

    /**
     * @return array<string, int|float>
     */
    public function getExpressionsByDensity(): array
    {
        $expressions = $this->expressions;
        foreach ($expressions as $k => $v) {
            $expressions[$k] = round(($v / $this->getWordNumber()) * 10000) / 100;
        }

        return $expressions;
    }

    public function getWordNumber(): int
    {
        return $this->wordNumber;
    }

    /**
     * @return array<string, int>
     */
    public function getExpressions(?int $number = null): array
    {
        return ! $number ? $this->expressions : \array_slice($this->getExpressions(), 0, $number);
    }
}
