<?php

namespace BrianHenryIE\PhpDiffTest;

/**
 * @coversDefaultClass \BrianHenryIE\PhpDiffTest\DiffCoverage
 */
class DiffCoverageCLITest extends \PHPUnit\Framework\TestCase
{
    public function testNameIsSet()
    {
        $sut = new DiffCoverageCLI();

        $this->assertEquals('coverage', $sut->getName());
    }
}
