<?php

namespace BrianHenryIE\PhpDiffTest;

/**
 * @coversDefaultClass \BrianHenryIE\PhpDiffTest\DiffCoverage
 */
class DiffFilterCLITest extends \PHPUnit\Framework\TestCase
{
    public function testNameIsSet()
    {
        $sut = new DiffFilterCLI();

        $this->assertEquals('filter', $sut->getName());
    }
}
