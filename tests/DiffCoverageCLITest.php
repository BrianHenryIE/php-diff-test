<?php

namespace BrianHenryIE\PhpDiffTest;

use Mockery;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversDefaultClass \BrianHenryIE\PhpDiffTest\DiffCoverage
 */
class DiffCoverageCLITest extends \PHPUnit\Framework\TestCase
{
    public function testNameIsSet()
    {

        $input = new ArgvInput(
            []
        );
        $output = Mockery::mock(OutputInterface::class);

        $sut = new DiffCoverageCLI();

        $this->assertEquals('coverage', $sut->getName());
    }
}
