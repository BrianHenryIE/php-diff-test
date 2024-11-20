<?php

/**
 * Modified from phpunit/php-code-coverage HTML report.
 *
 * @see \SebastianBergmann\CodeCoverage\Report\Html\Facade
 */

declare(strict_types=1);

namespace BrianHenryIE\PhpDiffTest\MarkdownReport;

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

    public function process(
        CodeCoverage $coverage,
        ?string $baseUrl, // The URL to prefix to each path
        ?string $outputPath = null,
    ): void {
        $output = '';

        $report = $coverage->getReport();

        $basePath = $report->pathAsString();

        $date   = date('D, M j, Y, G:i:s T');

        $directory = new Directory(
            $basePath,
            $baseUrl,
            $this->templatePath,
            $this->generator,
            $date,
            $this->thresholds,
            $coverage->collectsBranchAndPathCoverage(),
        );

        $output .= $directory->render($report);

        if ($outputPath) {
            file_put_contents($outputPath, $output);
        } else {
            // TODO: Should this return so Symfony output can write it?
            echo $output;
        }
    }
}
