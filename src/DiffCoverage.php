<?php

/**
 * Filters a code coverage file to include only the files contained in a diff.
 */

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\WriteOperationFailedException;
use SebastianBergmann\CodeCoverage\Driver\XdebugDriver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpReportWriter;
use stdClass;

class DiffCoverage
{
    protected DiffLines $diffLines;

    /**
     * @var PhpReportWriter|stdClass $reportWriter
     */
    protected $reportWriter;

    /**
     * @param string $cwd Current working directory, with trailing slash.
     * @param DiffLines $diffLines
     * @param PhpReportWriter|stdClass|null $reportWriter
     * @throws Exception
     */
    public function __construct(
        protected string $cwd,
        DiffLines $diffLines,
        PhpReportWriter|stdClass|null $reportWriter = null,
    ) {
        $this->diffLines = $diffLines;
        $this->reportWriter = $reportWriter ?? new PhpReportWriter();
    }

    /**
     * @param string[] $coverageFilePaths List of absolute or relative file paths to coverage files to merge.
     * @param string $diffFrom SHA or compatible reference to diff from.
     * @param string $diffTo SHA or compatible reference to diff to.
     * @param string $outputFile Full filepath to write the filtered coverage object to.
     * @throws Exception
     *
     * @throws WriteOperationFailedException
     */
    public function execute(array $coverageFilePaths, string $diffFrom, string $diffTo, string $outputFile): void
    {
        /** @var ?CodeCoverage $oldCoverage */
        $oldCoverage = $this->mergeCoverageFiles($coverageFilePaths);

        if (is_null($oldCoverage)) {
            throw new Exception("No `CodeCoverage` objects found in the provided files.");
        }

        $diffFilesLineRanges = $this->diffLines->getChangedLines(
            diffFrom: $diffFrom,
            diffTo: $diffTo,
            // Filter to only include PHP files that are not test files and not in the tests' directory.
            filePathFilter: fn($filepath) => $this->isNonTestPhpFile($filepath)
        );

        $diffFilePaths = array_keys($diffFilesLineRanges);

        $newCoverage = self::filterCoverage($oldCoverage, $diffFilePaths);

        $outputFilepath = $this->prepareOutputPath($outputFile);

        $this->reportWriter->process($newCoverage, $outputFilepath);
    }

    protected function isNonTestPhpFile(string $filePath): bool
    {
        return str_ends_with($filePath, '.php')
            && !str_ends_with($filePath, 'Test.php')
            && !str_starts_with($filePath, $this->cwd . 'tests');
    }

    /**
     * Takes an absolute or relative filepath, ensures the directory exists, and returns an absolute path.
     *
     * @param string $outputFile
     * @throws Exception
     */
    protected function prepareOutputPath(string $outputFile): string
    {
        $outputFilepath = str_starts_with($outputFile, '/')
            ? $outputFile
            : $this->cwd . $outputFile;

        if (!is_dir(dirname($outputFilepath))) {
            if (!mkdir(dirname($outputFilepath), 0777, true)) {
                throw new Exception("Failed to create directory: " . dirname($outputFilepath));
            }
        }

        return $outputFilepath;
    }

    /**
     * @param string[] $coverageFilePaths
     * @throws Exception
     */
    protected function mergeCoverageFiles(array $coverageFilePaths): ?CodeCoverage
    {
        return array_reduce(
            $coverageFilePaths,
            function (?CodeCoverage $mergedCoverage, string $coverageFilePath): ?CodeCoverage {
                try {
                    $coverage = (fn() => include $coverageFilePath )() ?: null;
                } catch (Exception $exception) {
                    throw new Exception(
                        sprintf(
                            "Coverage file: %s probably created with an incompatible PHPUnit version.",
                            $coverageFilePath
                        )
                    );
                }

                if ($mergedCoverage instanceof CodeCoverage && $coverage instanceof CodeCoverage) {
                    $mergedCoverage->merge($coverage);
                    return $mergedCoverage;
                }

                return $mergedCoverage ?? $coverage ?? null;
            }
        );
    }

    /**
     * Creates a new CodeCoverage object filtered to only files that are in the provided list.
     *
     * Works with full file paths or relative paths (matching the end of the file path).
     *
     * @param CodeCoverage $oldCoverage An existing CodeCoverage object.
     * @param string[] $coveredFilesList List of files to narrow the report to contain.
     */
    public static function filterCoverage(CodeCoverage $oldCoverage, array $coveredFilesList): CodeCoverage
    {
        if (empty($coveredFilesList)) {
            return $oldCoverage;
        }

        $data = $oldCoverage->getData();

        $lineCoverage = $data->lineCoverage();

        $filteredLineCoverage = [];
        foreach ($lineCoverage as $filepath => $lineData) {
            // Do full filepath match first.
            if (in_array($filepath, $coveredFilesList)) {
                $filteredLineCoverage[$filepath] = $lineData;
                continue;
            }
            // Then check for relative path.
            foreach ($coveredFilesList as $coveredFilePath) {
                if (str_ends_with($filepath, $coveredFilePath)) {
                    $filteredLineCoverage[$filepath] = $lineData;
                    continue 2; // No need to check other covered files
                }
            }
        }

        $diffFilePaths = array_keys($filteredLineCoverage);

        $filter = new Filter();
        $filter->includeFiles($diffFilePaths);
        // Would it be possible to edit the class with reflection instead?
        // This requires XDEBUG_MODE=coverage
        // In tests, XDEBUG_MODE=coverage,debug
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
        $newCoverageData->setLineCoverage($filteredLineCoverage);
        $newCoverage->setData($newCoverageData);
        $newCoverage->setTests($oldCoverage->getTests());

        return $newCoverage;
    }
}
