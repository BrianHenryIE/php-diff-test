<?php

namespace BrianHenryIE\PhpDiffTest;

use SebastianBergmann\CodeCoverage\CodeCoverage;

class DiffTest
{
    public function run($projectRootDir)
    {
        // Search $projectRootDir and two levels of tests for *.cov
        // If there are corresponding *.suite.yml, treat them as Codeception
        // otherwise treat them as PhpUnit.

        $coverageFilePath = $this->getCodeCoverageFilepaths($projectRootDir);

        // This happens after `codecept clean`.
        if (empty($coverageFilePath)) {
            error_log('No code coverage files found.');
            return 1;
        }

        /** @var array<string,string> $coverageSuiteNamesFilePaths The coverage file paths indexed by the presumed suite name. */
        $coverageSuiteNamesFilePaths = array();
        foreach ($coverageFilePath as $filePath) {
            // TODO: This is bad if multiple .cov have the same name in different directories.
            preg_match('/.*\/(.*).cov/', $filePath, $outputArray);
            $name = $outputArray[1];
            $coverageSuiteNamesFilePaths[$name] = $filePath;
        }

        $diffLines = new DiffLines();
        $diffFilesLineRanges = $diffLines->getChangedLines($projectRootDir);

        $fqdnTestsToRunBySuite = $this->getFqdnTestsToRunBySuite($coverageSuiteNamesFilePaths, $diffFilesLineRanges);

        if ($this->isCodeceptionRun($projectRootDir, array_keys($coverageSuiteNamesFilePaths))) {
            // codecept run wpunit ":API_WPUnit_Test:test_add_autologin_to_message"
            $classnameTestsToRunBySuite = array();
            foreach ($fqdnTestsToRunBySuite as $suiteName => $tests) {
                $classnameTestsToRunBySuite[$suiteName] = array_map(
                    array( $this, 'fqdnToCodeceptionFriendlyShortname' ),
                    $tests
                );
            }

            echo ':' . implode('|', array_unique(array_merge(...array_values($classnameTestsToRunBySuite))));
        } else {
            // phpunit --filter="BrianHenryIE\\\MoneroRpc\\\DaemonUnitTest::testOnGetBlockHash"
            echo str_replace(
                '\\',
                '\\\\',
                implode(
                    '|',
                    array_unique(array_merge(...array_values($fqdnTestsToRunBySuite)))
                )
            );
        }
    }

    private function isCodeceptionRun(string $projectRootDir, array $coverageSuiteNames): bool
    {
        return !empty(array_intersect(
            array_keys($this->getCodeceptionSuites($projectRootDir)),
            $coverageSuiteNames
        ));
    }

    private function getCodeceptionSuites(string $projectRootDir)
    {
        $codeceptionSuites      = array();
        $codeceptionSuitesFiles = glob($projectRootDir . '/tests/*.suite.y*ml');
        foreach ($codeceptionSuitesFiles as $filepath) {
            preg_match('/.*\/(.*)\.suite\.y.?ml/', $filepath, $output_array);
            $name                       = $output_array[1];
            $codeceptionSuites[ $name ] = $filepath;
        }
        return $codeceptionSuites;
    }

    /**
     * @param array $coverageSuiteNamesFilePaths
     * @param array $fqdnTestsToRunBySuite
     * @param array<string, array<int[]>> $diffFilesLineRanges
     * @return array
     */
    public function getFqdnTestsToRunBySuite(array $coverageSuiteNamesFilePaths, array $diffFilesLineRanges): array
    {
        $fqdnTestsToRunBySuite = array();
        foreach ($coverageSuiteNamesFilePaths as $suiteName => $coverageFilePath) {
            $fqdnTestsToRunBySuite[$suiteName] = array();

            /** @var CodeCoverage $coverage */
            $coverage = include $coverageFilePath;

            $srcFilesAbsolutePaths = array_keys($diffFilesLineRanges);

            $fqdnTestClassesAndMethods = array();
            $fqdnTestClassesAndFilepaths = array();
            $fqdnTestClassesAndShortname = array();

            $lineCoverage = $coverage->getData()->lineCoverage();



            foreach ($srcFilesAbsolutePaths as $srcAbspath) {
                if (!isset($lineCoverage[$srcAbspath])) {
                    continue;
                }
                foreach ($lineCoverage[$srcAbspath] as $lineNumber => $tests) {
                    if ($this->isNumberInRanges($lineNumber, $diffFilesLineRanges[$srcAbspath])) {
                        /**
                         * @var string $test is the FQDN string of the test for this line number
                         */
                        foreach ($tests as $test) {
                            $fqdnTestsToRunBySuite[$suiteName][] = $test;
                        }
                    }
                }
            }
        }
        return $fqdnTestsToRunBySuite; // array($suiteName, $fqdnTestsToRunBySuite, $tests);
    }

    protected function isNumberInRanges(int $number, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($this->isNumberInRange($number, $range)) {
                return true;
            }
        }
        return false;
    }
    protected function isNumberInRange(int $number, array $range): bool
    {
        return $number >= $range[0] && $number <= $range[1];
    }

    private function fqdnToCodeceptionFriendlyShortname(string $test)
    {
        list( $testFqdnClassName, $testMethod ) = explode('::', $test);
        $parts = explode('\\', $testFqdnClassName);
        $shortClass = array_pop($parts);
        return $shortClass . ':' . $testMethod;
    }

    /**
     * @param $projectRootDir
     *
     * @return array|false
     */
    public function getCodeCoverageFilepaths($projectRootDir): array
    {
        $covFiles = array_merge(
            glob($projectRootDir . '/*.cov'),
            glob($projectRootDir . '/tests/*.cov'),
            glob($projectRootDir . '/tests/*/*.cov')
        );

        return $covFiles ?: array();
    }
}
