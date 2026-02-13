<?php

declare(strict_types=1);

namespace PiedWeb\Rison\Test;

use PiedWeb\Rison as R;

class RisonEncoderTest extends \PHPUnit\Framework\TestCase
{
    public function testArrays(): void
    {
        $r = new R\RisonEncoder(['foo', 'bar']);
        $this->assertEquals('!(foo,bar)', $r->encode());
    }

    public function testObjects(): void
    {
        $r = new R\RisonEncoder(['foo' => 'bar']);
        $this->assertEquals('(foo:bar)', $r->encode());
    }

    public function testSimpleObject(): void
    {
        $php = ['a' => 0, 'b' => 1];
        $rison = '(a:0,b:1)';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testComplexObject(): void
    {
        $php = ['a' => 0, 'b' => 'foo', 'c' => '23skidoo'];
        $rison = "(a:0,b:foo,c:'23skidoo')";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testTrue(): void
    {
        $php = true;
        $rison = '!t';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testFalse(): void
    {
        $php = false;
        $rison = '!f';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testNull(): void
    {
        $php = null;
        $rison = '!n';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testEmptyString(): void
    {
        $php = '';
        $rison = "''";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function test0(): void
    {
        $php = 0;
        $rison = '0';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function test15(): void
    {
        $php = 1.5;
        $rison = '1.5';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testMinus3(): void
    {
        $php = -3;
        $rison = '-3';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function test1e30(): void
    {
        $php = 1e+30;
        $rison = '1e30';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function test1eMinus30(): void
    {
        $php = 1e-30;
        $rison = '1e-30';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testA(): void
    {
        $php = 'a';
        $rison = 'a';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function test0a(): void
    {
        $php = '0a';
        $rison = "'0a'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testAbcDef(): void
    {
        $php = 'abc def';
        $rison = "'abc def'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testEmptyObject(): void
    {
        $php = [];
        $rison = '()';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testSingleObject(): void
    {
        $php = ['a' => 0];
        $rison = '(a:0)';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testComplexQuoteObject(): void
    {
        $php = ['id' => null, 'type' => '/common/document'];
        $rison = '(id:!n,type:/common/document)';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testPrimitiveTypeArray(): void
    {
        $php = [true, false, null, ''];
        $rison = "!(!t,!f,!n,'')";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testMinusH(): void
    {
        $php = '-h';
        $rison = "'-h'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testAThroughZ(): void
    {
        $php = 'a-z';
        $rison = 'a-z';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testWow(): void
    {
        $php = 'wow!';
        $rison = "'wow!!'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testDomainDotCom(): void
    {
        $php = 'domain.com';
        $rison = 'domain.com';

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testUserAtDomainDotCom(): void
    {
        $php = 'user@domain.com';
        $rison = "'user@domain.com'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function test10Dollars(): void
    {
        $php = 'US $10';
        $rison = "'US $10'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testCant(): void
    {
        $php = "can't";
        $rison = "'can!'t'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testControlF(): void
    {
        $php = 'Control-F: ';
        $rison = "'Control-F: '";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testUnicode(): void
    {
        $php = 'Unicode: ௫';
        $rison = "'Unicode: ௫'";

        $this->assertEquals($rison, R\rison_encode($php));
    }

    public function testAllTypesNested(): void
    {
        $php = ['foo' => 'bar', 'baz' => [1, 12e40, 0.42, ['a' => true, false, null]]];
        $rison = '(baz:!(1,1.2e41,0.42,(0:!f,1:!n,a:!t)),foo:bar)';

        $this->assertEquals($rison, R\rison_encode($php));
    }
}
