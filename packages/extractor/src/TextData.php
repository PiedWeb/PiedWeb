<?php

namespace PiedWeb\Extractor;

use PiedWeb\TextAnalyzer\Analysis;
use PiedWeb\TextAnalyzer\Analyzer as TextAnalyzer;
use Symfony\Component\DomCrawler\Crawler;

final class TextData
{
    public function __construct(
        private Crawler $crawler,
        private string $html
    ) {
    }

    /** @psalm-suppress RedundantPropertyInitializationCheck */
    public function getTextAnalysis(): ?Analysis
    {
        return $this->crawler->count() > 0 ? (new TextAnalyzer($this->crawler->text(), true, 1))->exec() : null;
    }

    public function getWordCount(): int
    {
        return str_word_count($this->crawler->text(''));
    }

    public function getRatioTxtCode(): int
    {
        $textLenght = \strlen($this->crawler->text(''));
        $htmlLenght = \strlen(Helper::clean($this->html));

        return (int) ($htmlLenght > 0 ? round($textLenght / $htmlLenght * 100) : 0);
    }
}
