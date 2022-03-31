<?php

namespace PiedWeb\TextAnalyzer\Test;

use PiedWeb\TextAnalyzer\Analyzer;
use PiedWeb\TextAnalyzer\MultiAnalyzer;

class AnalyzerTest extends \PHPUnit\Framework\TestCase
{
    public function testMultiAnalyzer()
    {
        $test = new MultiAnalyzer(true);

        $result = $test->addContent('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed. Lorem ipsum 2 dolor sit amet, consectetur adipiscing elit, sed...');
        $this->assertSame($result->getExpressions()['dolor sit amet'], 6);
        $this->assertSame($result->getWordNumber(), 18);

        $result = $test->addContent('Text Analyser : Expression in a text per Usage.');
        $result = $test->addContent('Please check if test are still running without error (phpunit)');
        $results = $test->exec();

        $this->assertSame($results->getExpressions()['dolor sit amet'], 6);
        $this->assertSame($results->getWordNumber(), 24);

        $content = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit'
                    .' sed. Text Analyser : Expression in a text per Usage.';

        $kws = new Analyzer($content,  false,  1);
        $kws = $kws->exec();
        $this->assertSame(\count($kws->getExpressions(10)), 10);
    }
}
