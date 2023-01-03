<?php

namespace PiedWeb\TextAnalyzer\Test;

use PiedWeb\TextAnalyzer\Analyzer;
use PiedWeb\TextAnalyzer\MultiAnalyzer;

class AnalyzerTest extends \PHPUnit\Framework\TestCase
{
    public function testMultiAnalyzer(): void
    {
        $test = new MultiAnalyzer(true);

        $result = $test->addContent('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed. Lorem ipsum 2 dolor sit amet, consectetur adipiscing elit, sed...');
        $this->assertSame($result->getExpressions()['dolor sit amet'], 2);
        $this->assertSame($result->getWordNumber(), 18);

        $result = $test->addContent('Text Analyser : Expression in a text per Usage.');
        $result = $test->addContent('Please check if test are still running without error (phpunit)');

        $results = $test->exec();

        $this->assertSame($results->getExpressions()['dolor sit amet'], 2);
        $this->assertSame($results->getWordNumber(), 24);

        $content = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit'
                    .' sed. Text Analyser : Expression&nbsp;in a text per Usage.';

        $kws = new Analyzer($content,  false,  1);
        $kws = $kws->exec();
        $this->assertCount(1, $kws->getExpressions(2));
    }

    public function testTextAnalyzer(): void
    {
        $text = 'chaque fois, c est la même histoire de chaque pluie, c est pas fini chaque matin';
        $text = $text.' '.$text.' '.$text;

        $tester = new Analyzer($text, false, 2);
        dump($tester->exec()->getExpressions(2));

        $this->assertTrue(true);
    }
}
