<?php

/**
 * Filters a code coverage file to include only the files contained in a diff.
 */

namespace BrianHenryIE\PhpDiffTest;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\WriteOperationFailedException;
use SebastianBergmann\CodeCoverage\Driver\XdebugDriver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpReportWriter;

class DiffCoverage
{
    protected DiffLines $diffLines;
    protected PhpReportWriter $reportWriter;

    /**
     * @param ?DiffLines $diffLines
     * @param ?PhpReportWriter $reportWriter
     * @throws \Exception
     */
    public function __construct(
        protected string $cwd, // Current working directory, with trailing slash.
        ?DiffLines $diffLines = null,
        ?PhpReportWriter $reportWriter = null,
    ) {
        $this->diffLines = $diffLines ?? new DiffLines($this->cwd);
        $this->reportWriter = $reportWriter ?? new PhpReportWriter();
    }

    /**
     * @throws WriteOperationFailedException
     */
    public function execute(array $coverageFilePaths, string $diffFrom, string $diffTo, string $outputFile): void
    {

        // Merge the coverage files
        /** @var CodeCoverage $oldCoverage */
        $oldCoverage = array_reduce(
            $coverageFilePaths,
            function (?CodeCoverage $mergedCoverage, string $coverageFilePath): CodeCoverage {
                $coverage = include $coverageFilePath;

                if (is_null($mergedCoverage)) {
                    return $coverage;
                }

                $mergedCoverage->merge($coverage);
                return $mergedCoverage;
            },
            null
        );

        $diffFilesLineRanges = $this->diffLines->getChangedLines(
            diffFrom: $diffFrom,
            diffTo: $diffTo,
            filePathFilter: fn($filePath) => str_ends_with($filePath, '.php')
        );

        /**
         * List of file paths contained in the diff, with files in the /tests directory removed.
         *
         * @var string[] $diffFilePaths
         */
        $diffFilePaths = array_filter(
            array_keys($diffFilesLineRanges),
            function (string $filepath): bool {
                return ! str_starts_with($filepath, $this->cwd . 'tests');
            }
        );


        /** @var ProcessedCodeCoverageData $oldCoverageData */
        $oldCoverageData = $oldCoverage->getData();

        /** @var array<string, array> $lineCoverage Indexed by filepath */
        $lineCoverage = $oldCoverageData->lineCoverage();

        foreach ($lineCoverage as $filepath => $lines) {
            if (!in_array($filepath, $diffFilePaths, true)) {
                unset($lineCoverage[$filepath]);
            }
            unset($lines);
        }


        $filter = new Filter();
        $filter->includeFiles($diffFilePaths);
        $xdebugDriver = new XdebugDriver(
            $filter
        );
        if ($oldCoverage->collectsBranchAndPathCoverage()) {
            $xdebugDriver->enableBranchAndPathCoverage();
        } else {
            $xdebugDriver->disableBranchAndPathCoverage();
        }
        $newCoverage = new CodeCoverage(
            $xdebugDriver,
            $filter
        );
        unset($xdebugDriver, $filter);

        $newCoverageData = $newCoverage->getData();
        $newCoverageData->setLineCoverage($lineCoverage);
        $newCoverage->setData($newCoverageData);
        $newCoverage->setTests($oldCoverage->getTests());

        unset($oldCoverage, $newCoverageData, $lineCoverage);

        $outputFilepath = str_starts_with('/', $outputFile)
            ? $outputFile
            : $this->cwd . $outputFile;

        if (!is_dir(dirname($outputFilepath))) {
            if (!mkdir(dirname($outputFilepath), 0777, true)) {
                throw new \Exception("Failed to create directory: " . dirname($outputFilepath));
            }
        }

        $this->reportWriter->process($newCoverage, $outputFilepath);
    }
}
