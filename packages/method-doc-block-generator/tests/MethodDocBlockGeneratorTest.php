<?php

declare(strict_types=1);

namespace PiedWeb\MethodDocBlockGenerator\Test;

use PiedWeb\MethodDocBlockGenerator\MethodDocBlockGenerator;

class MethodDocBlockGeneratorTest extends \PHPUnit\Framework\TestCase
{
    public function testIt(): void
    {
        $generatedDocblock = (new MethodDocBlockGenerator())->run(MethodDocBlockGenerator::class);

        $this->assertStringContainsString(' * @method string run(string $extensionClassName)', $generatedDocblock);

        $generatedDocblock = (new MethodDocBlockGenerator(false))->run(MethodDocBlockGenerator::class);
        $this->assertSame(' * @method string run(string $extensionClassName)'."\n", $generatedDocblock);
    }
}
