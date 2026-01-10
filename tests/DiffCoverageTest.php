<?php

namespace BrianHenryIE\PhpDiffTest;

use Mockery;
use Mockery\MockInterface;
use SebastianBergmann\CodeCoverage\CodeCoverage;

/**
 * @coversDefaultClass \BrianHenryIE\PhpDiffTest\DiffCoverage
 */
class DiffCoverageTest extends IntegrationTestCase
{
    public function testInput(): void
    {
        $cwd = getcwd() ?: __DIR__; // tmp?
        /** @var DiffLines|MockInterface $diffLines */
        $diffLines = Mockery::mock(DiffLines::class);
        $diffLines->expects('getChangedLines')->once()->andReturn([]);

        /**
         * Cannot mock {@see \SebastianBergmann\CodeCoverage\Report\PHP} final class, but it does not implement any
         * interface so we use a mock of stdClass here.
         */
        $reportWriter = Mockery::mock(\stdClass::class);
        $reportWriter->expects('process')->once()->with(
            Mockery::type(\SebastianBergmann\CodeCoverage\CodeCoverage::class),
            Mockery::type('string')
        );

        $coverageFilePath = dirname(__FILE__, 1) . '/Fixtures/php.cov';
        $coverageFilesPaths = [ $coverageFilePath ];

        $sut = new DiffCoverage($cwd, $diffLines, $reportWriter,);

        $diffFrom = 'main';
        $diffTo = 'HEAD~0';
        $outputFile = $this->testsWorkingDir . __FUNCTION__ . '/result.cov.php';

        $sut->execute($coverageFilesPaths, $diffFrom, $diffTo, $outputFile);

        // If this were an integration tests:
//        $this->assertFileExists($outputFile);
//        /** @var CodeCoverage $result */
//        $result = include $outputFile;

        // TODO: actually validate the result in $reportWriter->expects.
        $this->expectNotToPerformAssertions();
    }
}
