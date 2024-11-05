<?php
/**
 * Filters a code coverage file to only include the files contained in a diff.
 */

namespace BrianHenryIE\PhpDiffTest;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\XdebugDriver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpReport;

class DiffCoverage
{
    public function run($projectRootDir): int
    {
        // Search $projectRootDir and two levels of tests for *.cov
        // If there are corresponding *.suite.yml, treat them as Codeception
        // otherwise treat them as PhpUnit.

        $coverageFilePaths = $this->getCodeCoverageFilepaths($projectRootDir);

        // This happens after `codecept clean`.
        if (empty($coverageFilePaths)) {
            error_log('No code coverage files found.');
            return 1;
        }


        $diffLines = new DiffLines();
        $diffFilesLineRanges = $diffLines->getChangedLines($projectRootDir);

        $diffFilePaths = array_keys($diffFilesLineRanges);

        $diffFilePaths = array_filter( $diffFilePaths, function( string $filepath ) use ($projectRootDir ): bool{
            return ! str_starts_with($filepath, $projectRootDir . '/tests');
        });

        foreach($coverageFilePaths as $coverageFilePath) {
            /** @var CodeCoverage $oldCoverage */
            $oldCoverage = include $coverageFilePath;

            /** @var ProcessedCodeCoverageData $oldCoverageData */
            $oldCoverageData = $oldCoverage->getData();

            /** @var array<string, array> $lineCoverage Indexed by filepath */
            $lineCoverage = $oldCoverageData->lineCoverage();

            foreach($lineCoverage as $filepath => $lines ) {
                if(!in_array($filepath, $diffFilePaths, true )){
                    unset($lineCoverage[$filepath]);
                }
                unset($lines);
            }

            $filter = new Filter();
            $filter->includeFiles($diffFilePaths);
            $xdebugDriver = new XdebugDriver(
                $filter
            );
            if($oldCoverage->collectsBranchAndPathCoverage()) {
                $xdebugDriver->enableBranchAndPathCoverage();
            }else {
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


            $outputFilepath = $projectRootDir . '/../coverage-temp/diff-main.cov';
            $writer = new PhpReport;
            $writer->process($newCoverage, $outputFilepath);

            unset($newCoverage, $outputFilepath);
        }

        return 0;
    }

    /**
     * @param $projectRootDir
     *
     * @return array
     */
    public function getCodeCoverageFilepaths(string $projectRootDir): array
    {
        return array_filter(array_merge(
            glob($projectRootDir . '/*.cov') ?: [],
            glob($projectRootDir . '/tests/*.cov') ?: [],
            glob($projectRootDir . '/tests/*/*.cov') ?: [],
        ));
    }
}
