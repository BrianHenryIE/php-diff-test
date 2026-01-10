<?php

namespace BrianHenryIE\PhpDiffTest\MarkdownReport;

use SebastianBergmann\CodeCoverage\CodeCoverage;

/**
 * @coversDefaultClass \BrianHenryIE\PhpDiffTest\MarkdownReport\MarkdownReport
 */
class MarkdownReportTest extends \PHPUnit\Framework\TestCase
{
    public function testCoverageMetrics()
    {
        /** @var CodeCoverage $coverage */
        $coverageFilePath = dirname(__FILE__, 2) . '/Fixtures/php.cov';
        $coverage = include $coverageFilePath;

        // Get the files changed in the diff.

        $data = $coverage->getData();

        $filePaths = $data->coveredFiles();

        // Text(Thresholds $thresholds, bool $showUncoveredFiles = false, bool $showOnlySummary = false)
//        $text = new \SebastianBergmann\CodeCoverage\Report\Text(
//            thresholds: Thresholds::default(),
//        );

        $text = new MarkdownReport();

        $baseUrl = null;
        $coveredFilesList = [];
        $result = $text->process($coverage, '/Users/brian.henry/Sites/php-diff-test/', $baseUrl, $coveredFilesList);


        // TODO: write a real test.
        $this->expectNotToPerformAssertions();
    }

    /**
     * @covers ::process
     */
    public function test_files_filter(): void
    {
        $coveredFilesList = [
            'src/DiffCoverage.php',
            'src/DiffCoverageCLI.php',
        ];

        $coverageFilePath = dirname(__FILE__, 2) . '/Fixtures/php.cov';
        /** @var CodeCoverage $coverage */
        $coverage = include $coverageFilePath;

        $text = new MarkdownReport();

        $result = $text->process($coverage, '/Users/brian.henry/Sites/php-diff-test/', null, $coveredFilesList);

        $this->assertStringNotContainsString(
            'src/DiffFilter.php',
            $result
        );

        $this->assertStringContainsString(
            'DiffCoverage.php',
            $result
        );
    }
}
