<?php

namespace PiedWeb\TextAnalyzer;

final class Analysis
{
    /**
     * @param array<string, int> $expressions
     */
    public function __construct(
        private readonly array $expressions,
        private readonly int $wordNumber = 0,
    ) {
    }

    /**
     * @return array<string, int|float>
     */
    public function getExpressionsByDensity(): array
    {
        $expressions = $this->expressions;
        foreach ($expressions as $k => $v) {
            $expressions[$k] = round(($v / $this->wordNumber) * 10000) / 100;
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
    public function getExpressions(int $minFound = null): array
    {
        return $minFound ? array_filter(
            $this->getExpressions(),
            static fn ($value): bool => $value >= $minFound
        ) : $this->expressions;
    }
}
