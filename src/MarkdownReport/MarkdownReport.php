<?php

/**
 * Modified from phpunit/php-code-coverage HTML report.
 *
 * @see \SebastianBergmann\CodeCoverage\Report\Html\Facade
 */

declare(strict_types=1);

namespace BrianHenryIE\PhpDiffTest\MarkdownReport;

use BrianHenryIE\PhpDiffTest\DiffCoverage;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

class MarkdownReport
{
    private readonly string $templatePath;
    private readonly string $generator;
    private readonly Thresholds $thresholds;

    public function __construct(
        string $generator = '',
        ?Thresholds $thresholds = null
    ) {
        $this->generator     = $generator;
        $this->thresholds    = $thresholds ?? Thresholds::default();
        $this->templatePath  = __DIR__ . '/MarkdownTemplate/';
    }

    /**
     * @param CodeCoverage $coverage
     * @param string|null $baseUrl
     * @param string[] $coveredFilesList
     * @return string
     */
    public function process(
        CodeCoverage $coverage,
        string $projectRoot, // The file path string to remove before prepending the base URL
        ?string $baseUrl, // The URL to prefix to each path
        array $coveredFilesList = [] // List of files to include in the report, or null for all files
    ): string {
        $filteredCoverage = DiffCoverage::filterCoverage($coverage, $coveredFilesList);

        $report = $filteredCoverage->getReport();

        $basePath = $report->pathAsString() . '/';

        $date   = date('D, M j, Y, G:i:s T');

        $directory = new Directory(
            $projectRoot,
            $baseUrl,
            $basePath,
            $this->templatePath,
            $this->generator,
            $date,
            $this->thresholds,
            $coverage->collectsBranchAndPathCoverage(),
        );

        return $directory->render($report);
    }
}
