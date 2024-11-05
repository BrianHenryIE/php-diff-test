<?php

namespace BrianHenryIE\PhpDiffTest;

use SebastianBergmann\CodeCoverage\CodeCoverage;

class DiffFilter
{
    private DiffLines $diffLines;

    public function __construct(
        protected string $cwd, // Current working directory, with trailing slash.
        ?DiffLines $diffLines = null,
    ) {
        $this->diffLines = $diffLines ?? new DiffLines($this->cwd);
    }

    public function execute(array $coverageFilePaths, string $diffFrom, string $diffTo): void
    {
        // Search $projectRootDir and two levels of tests for *.cov
        // If there are corresponding *.suite.yml, treat them as Codeception
        // otherwise treat them as PhpUnit.


        /** @var array<string,string> $coverageSuiteNamesFilePaths The coverage file paths indexed by the presumed suite name. */
        $coverageSuiteNamesFilePaths = array();
        foreach ($coverageFilePaths as $filePath) {
            // TODO: This is bad if multiple .cov have the same name in different directories.
            preg_match('/.*\/(.*).cov/', $filePath, $outputArray);
            $name = $outputArray[1];
            $coverageSuiteNamesFilePaths[$name] = $filePath;
        }

        $diffFilesLineRanges = $this->diffLines->getChangedLines($diffFrom, $diffTo);

        $fqdnTestsToRunBySuite = $this->getFqdnTestsToRunBySuite($coverageSuiteNamesFilePaths, $diffFilesLineRanges);

        if ($this->isCodeceptionRun($this->cwd, array_keys($coverageSuiteNamesFilePaths))) {
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

    /**
     * @param string $projectRootDir
     * @return array<string,string> <suite name, filepath>
     */
    private function getCodeceptionSuites(string $projectRootDir): array
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

    /**
     * @param int $number
     * @param array<array{0:int, 1:int}> $ranges
     */
    protected function isNumberInRanges(int $number, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($this->isNumberInRange($number, $range)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $number
     * @param array{0:int, 1:int}  $range
     */
    protected function isNumberInRange(int $number, array $range): bool
    {
        return $number >= $range[0] && $number <= $range[1];
    }

    private function fqdnToCodeceptionFriendlyShortname(string $test): string
    {
        list( $testFqdnClassName, $testMethod ) = explode('::', $test);
        $parts = explode('\\', $testFqdnClassName);
        $shortClass = array_pop($parts);
        return $shortClass . ':' . $testMethod;
    }
}
