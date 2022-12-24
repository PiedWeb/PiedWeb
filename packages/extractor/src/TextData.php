<?php

namespace PiedWeb\Extractor;

use PiedWeb\TextAnalyzer\Analysis;
use PiedWeb\TextAnalyzer\Analyzer as TextAnalyzer;
use PiedWeb\TextAnalyzer\CleanText;
use Symfony\Component\DomCrawler\Crawler;

final class TextData
{
    public function __construct(
        private readonly string $html,
        private readonly ?Crawler $crawler = null,
    ) {
    }

    /** @psalm-suppress RedundantPropertyInitializationCheck */
    public function getTextAnalysis(): Analysis
    {
        return (new TextAnalyzer($this->getText(), false, 4))->exec();
    }

    private ?string $text = null;

    private function getText(): string
    {
        if (null !== $this->text) {
            return $this->text;
        }

        if (null === $this->crawler) {
            return $this->html;
        }

        $this->text = '';
        $elements = $this->crawler->filterXPath(self::getXPathToSelectNodeContent());
        foreach ($elements as $element) {
            $this->text .= ' '.CleanText::fixEncoding($element->textContent);
        }

        return $this->text;
    }

    public static function getXPathToSelectNodeContent(string $tag = 'p,h1,h2,h3,h4,h5,h6,li,div'): string
    {
        $tagsToGet = explode(',', $tag);
        $xpath = '//head/title';
        $not = '[not(self::node()[count(.//'.implode('|.//', $tagsToGet).') > 0])]';
        foreach ($tagsToGet as $tag) {
            $xpath .= ' | //'.$tag.$not;
        }

        return $xpath;
    }

    public function getWordCount(): int
    {
        return str_word_count($this->getText());
    }

    /** @return array<string, string> */
    public function getFlatContent(): array
    {
        if (null === $this->crawler) {
            throw new \Exception();
        }

        $flatContent = [];
        $elements = $this->crawler->filterXPath(self::getXPathToSelectNodeContent('p,h1,h2,h3,h4,h5,h6,li'));

        foreach ($elements as $node) {
            $text = CleanText::fixEncoding($node->textContent);
            if (isset($flatContent[$text])) {
                continue;
            }

            $flatContent[$text] = $node->nodeName;
        }

        return $flatContent;
    }

    public function getRatioTxtCode(): int
    {
        $textLenght = \strlen($this->getText());
        $htmlLenght = \strlen(CleanText::fixEncoding($this->html));

        return (int) ($htmlLenght > 0 ? round($textLenght / $htmlLenght * 100) : 0);
    }
}
