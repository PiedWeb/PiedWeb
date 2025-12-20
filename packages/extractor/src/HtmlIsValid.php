<?php

namespace PiedWeb\Extractor;

final class HtmlIsValid
{
    /**
     * @var array<string, int>
     */
    public const INVALID_REASONS = [
        'no closing html' => 1,
        'no closing body' => 2,
        'no closing head' => 3,
        'no doctype' => 4,
        'no html tag' => 5,
        'no head tag' => 6,
        'no body tag' => 7,
        'unclosed tags' => 8,
    ];

    /** @var int[] */
    private array $invalidReasons = [];

    public function __construct(
        private readonly string $html
    ) {
        $this->analyze();
    }

    public static function invalidReasonLabel(int $code): string
    {
        $labels = array_flip(self::INVALID_REASONS);

        return $labels[$code] ?? 'unknown';
    }

    public function isValid(): bool
    {
        return [] === $this->invalidReasons;
    }

    /**
     * @return int[]
     */
    public function getInvalidReasonList(): array
    {
        return $this->invalidReasons;
    }

    /**
     * @return string[]
     */
    public function getInvalidReasonLabels(): array
    {
        return array_map(
            self::invalidReasonLabel(...),
            $this->invalidReasons
        );
    }

    private function analyze(): void
    {
        $html = $this->html;
        $htmlLower = strtolower($html);

        if (! str_contains($htmlLower, '<!doctype')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no doctype'];
        }

        if (! str_contains($htmlLower, '<html')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no html tag'];
        }

        if (! str_contains($htmlLower, '</html>')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no closing html'];
        }

        if (! str_contains($htmlLower, '<head')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no head tag'];
        }

        if (! str_contains($htmlLower, '</head>')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no closing head'];
        }

        if (! str_contains($htmlLower, '<body')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no body tag'];
        }

        if (! str_contains($htmlLower, '</body>')) {
            $this->invalidReasons[] = self::INVALID_REASONS['no closing body'];
        }

        if ($this->hasUnclosedTags($htmlLower)) {
            $this->invalidReasons[] = self::INVALID_REASONS['unclosed tags'];
        }
    }

    private function hasUnclosedTags(string $htmlLower): bool
    {
        $tagsToCheck = ['div', 'p', 'span', 'a', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'form', 'section', 'article', 'header', 'footer', 'nav', 'main', 'aside'];

        foreach ($tagsToCheck as $tag) {
            $openCount = preg_match_all('/<'.$tag.'[\s>]/i', $htmlLower);
            $closeCount = preg_match_all('/<\/'.$tag.'>/i', $htmlLower);

            if ($openCount !== $closeCount) {
                return true;
            }
        }

        return false;
    }
}
