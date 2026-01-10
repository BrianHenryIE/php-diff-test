<?php

namespace BrianHenryIE\PhpDiffTest;

use Mockery;

class DiffFilterTest extends IntegrationTestCase
{
    public function testTestLine(): void
    {

        $lines = [
            __FILE__ => [[ __LINE__ - 2, __LINE__ + 2]]
        ];

        $diffLines = Mockery::mock(DiffLines::class);

        $diffLines->expects('getChangedLines')->once()
            ->andReturn($lines);

        $sut = new DiffFilter($this->testsWorkingDir, $diffLines);

        $coverageFilePath = dirname(__FILE__, 1) . '/Fixtures/php.cov';
        $coverageFilesPaths = [ $coverageFilePath ];

        $result = $sut->execute($coverageFilesPaths, 'main', 'HEAD~0', Granularity::LINE);

        // TODO: write a real test.
        $this->expectNotToPerformAssertions();
    }

    public function testOne(): void
    {
        $diffFrom = sha1('diffFrom');
        $diffTo = sha1('diffTo');

        $lines = [
            '' => []
        ];

        $diffLines = Mockery::mock(DiffLines::class);
        $diffLines->expects('getChangedLines')->once()
//            ->with($diffFrom, $diffTo)
            ->andReturn($lines);

        $sut = new DiffFilter($this->testsWorkingDir, $diffLines);

        $coverageFilePaths = [];

        $result = $sut->execute($coverageFilePaths, $diffFrom, $diffTo);

        // TODO: write a real test.
        $this->expectNotToPerformAssertions();
    }
}
