<?php

/**
 * Creates a filter to use with PHPUnit or Codeception to run tests.
 *
 * Inputs are PHP code coverage files and commit hashes to compare between.
 * Output is a string.
 */

namespace BrianHenryIE\PhpDiffTest;

use BrianHenryIE\PhpDiffTest\DiffFilter\TestMethodRecorderVisitor;
use Exception;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use SebastianBergmann\CodeCoverage\CodeCoverage;

class DiffFilter
{
    /**
     * Utility to determine the changed lines.
     */
    private DiffLines $diffLines;

    public function __construct(
        protected string $cwd, // Repository working directory, with trailing slash.
        ?DiffLines $diffLines = null,
    ) {
        $this->diffLines = $diffLines ?? new DiffLines($this->cwd);
    }

    /**
     *
     *
     * @param string[] $coverageFilePaths
     * @param string $diffFrom
     * @param string $diffTo
     * @param Granularity $granularity
     * @return string
     * @throws Exception
     */
    public function execute(
        array $coverageFilePaths,
        string $diffFrom,
        string $diffTo,
        Granularity $granularity = Granularity::LINE
    ): string {

        /** @var array<string,string> $coverageSuiteNamesFilePaths The coverage file paths indexed by the presumed suite name. */
        $coverageSuiteNamesFilePaths = [];
        foreach ($coverageFilePaths as $filePath) {
            if (1 === preg_match('/.*\/(.*).cov/', $filePath, $outputArray)) {
                $name = $outputArray[1];
                // TODO: This is bad if multiple .cov have the same name in different directories.
                $coverageSuiteNamesFilePaths[$name] = $filePath;
            }
        }

        $phpFilesFilter = fn($filePath) => str_ends_with($filePath, '.php');

        $diffFilesLineRanges = $this->diffLines->getChangedLines(
            $diffFrom,
            $diffTo,
            $phpFilesFilter
        );

        $fqdnDiffTests = $this->getTestsChangedInDiff($diffFilesLineRanges);

        $fqdnTestsToRunBySuite = $this->getFqdnTestsToRunBySuite(
            $coverageSuiteNamesFilePaths,
            $diffFilesLineRanges,
            $granularity,
        );

        // Tests with data providers are indexed by test_name#dataprovider which has caused problems,
        // so let's just run those tests with all the values
        $removeDataProviderIndex = fn(string $testName) => explode('#', $testName)[0];

        if ($this->isCodeceptionRun($this->cwd, array_keys($coverageSuiteNamesFilePaths))) {
            $classnameTestsToRunBySuite = array();
            foreach ($fqdnTestsToRunBySuite as $suiteName => $tests) {
                $classnameTestsToRunBySuite[$suiteName] = array_map(
                    array( $this, 'fqdnToCodeceptionFriendlyShortname' ),
                    $tests
                );
            }

            return ':' . implode('|', array_unique(array_merge(...array_values($classnameTestsToRunBySuite))));
        } else {
            $fqdnTests = array_unique(
                array_map(
                    $removeDataProviderIndex,
                    // `array_values` here just to make it easier to read when step debugging.
                    array_values(array_merge($fqdnDiffTests, ...array_values($fqdnTestsToRunBySuite)))
                )
            );
            return str_replace(
                '\\',
                '\\\\',
                implode(
                    '|',
                    $fqdnTests
                )
            );
        }
    }

    /**
     * If there are corresponding *.suite.yml for the provided coverage files, this is likely used with Codeception.
     *
     * @param string $projectRootDir
     * @param string[] $coverageSuiteNames
     * @return bool
     */
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
        $codeceptionSuitesFiles = glob($projectRootDir . '/tests/*.suite.y*ml') ?: [];
        foreach ($codeceptionSuitesFiles as $filepath) {
            if (1 === preg_match('/.*\/(.*)\.suite\.y.?ml/', $filepath, $output_array)) {
                $name                       = $output_array[1];
                $codeceptionSuites[ $name ] = $filepath;
            }
        }
        return $codeceptionSuites;
    }

    /**
     * Using the diff lines and the code coverage files, return the tests that were run against the modified lines.
     *
     * @param string[] $coverageSuiteNamesFilePaths
     * @param array<string, array<array{0:int,1:int}>> $diffFilesLineRanges
     * @param Granularity $granularity Return tests which cover the specific lines, or anywhere in the modified file.
     * @return array<string, string[]> array of arrays, indexed by suite name, of FQDN test cases
     */
    protected function getFqdnTestsToRunBySuite(
        array $coverageSuiteNamesFilePaths,
        array $diffFilesLineRanges,
        Granularity $granularity
    ): array {
        $fqdnTestsToRunBySuite = array();
        foreach ($coverageSuiteNamesFilePaths as $suiteName => $coverageFilePath) {
            $fqdnTestsToRunBySuite[$suiteName] = $this->getFqdnTestsToRun(
                $coverageFilePath,
                $diffFilesLineRanges,
                $granularity
            );
        }
        return $fqdnTestsToRunBySuite;
    }

    /**
     * Using the diff lines and the code coverage file, return the tests that were run against the modified lines.
     *
     * @param string $coverageFilePath
     * @param array<string, array<array{0:int,1:int}>> $diffFilesLineRanges
     * @param Granularity $granularity Return tests which cover the specific lines, or anywhere in the modified file.
     * @return string[] FQDN test cases
     */
    protected function getFqdnTestsToRun(
        string $coverageFilePath,
        array $diffFilesLineRanges,
        Granularity $granularity
    ): array {
        $fqdnTestsToRunBySuite = array();

        /** @var CodeCoverage $coverage */
        $coverage = include $coverageFilePath;

        $srcFilesAbsolutePaths = array_keys($diffFilesLineRanges);

        /**
         * Array indexed by filepath of covered file, containing array of lines in that file, containing an array
         * of FQDN test cases that cover that line.
         *
         * @var array<string, array<int,array<string>>> $lineCoverage
         */
        $lineCoverage = $coverage->getData()->lineCoverage();

        foreach ($srcFilesAbsolutePaths as $srcAbspath) {
            if (!isset($lineCoverage[$srcAbspath])) {
                continue;
            }
            foreach ($lineCoverage[$srcAbspath] as $lineNumber => $tests) {
                switch ($granularity) {
                    case Granularity::FILE:
                        foreach ($tests as $test) {
                            $fqdnTestsToRunBySuite[$test] = $test;
                        }
                        break;
                    case Granularity::LINE:
                        if ($this->isNumberInRanges($lineNumber, $diffFilesLineRanges[$srcAbspath])) {
                            /**
                             * @var string $test is the FQDN string of the test for this line number
                             */
                            foreach ($tests as $test) {
                                $fqdnTestsToRunBySuite[$test] = $test;
                            }
                        }
                        break;
                }
            }
        }
        return $fqdnTestsToRunBySuite;
    }

    /**
     * Determine is a given number within in any of the given ranges.
     *
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
     * Check if a number is within a given range, inclusive of the endpoints.
     *
     * @param int $number
     * @param array{0:int, 1:int} $range
     */
    protected function isNumberInRange(int $number, array $range): bool
    {
        sort($range);
        return $number >= $range[0] && $number <= $range[1];
    }

    /**
     * Remove the namespace from the test name and separate the class name and method with a single colon.
     *
     * `BrianHenryIE\PhpDiffTest\DiffLinesTest::testGetChangedFiles` -> `DiffLinesTest:testGetChangedFiles`
     *
     * `preg_replace('/.*\\(.*)::(.*)/', '$1:$2', $fqdnTestMethod)`
     *
     * @param string $fqdnTestMethod
     */
    private function fqdnToCodeceptionFriendlyShortname(string $fqdnTestMethod): string
    {
        list( $testFqdnClassName, $testMethod ) = explode('::', $fqdnTestMethod);
        $parts = explode('\\', $testFqdnClassName);
        $shortClass = array_pop($parts);
        return $shortClass . ':' . $testMethod;
    }

    /**
     * Get a list of test methods that were changed in the diff.
     *
     * Given the changes lines in a diff, determines if any of those lines intersect with test methods.
     *
     * Shortcoming: if a data provider is given more cases but the test method itself is not changed, it will not
     * be included.
     *
     * @param array<string, array<array{0:int,1:int}>> $diffLinesPhp Index: filepath, array of ranges of changed lines.
     * @return array<string> FQDN test cases
     */
    private function getTestsChangedInDiff(array $diffLinesPhp): array
    {
        $testFilesFilter = fn(string $filePath): bool => str_ends_with($filePath, 'Test.php');

        $testFilesLines = array_filter($diffLinesPhp, $testFilesFilter, ARRAY_FILTER_USE_KEY);

        $tests = [];

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        foreach ($testFilesLines as $filePath => $lines) {
            if (!is_readable($filePath)) {
                // The code coverage report is out of sync with the branch. E.g. it was generated on a different branch.
                continue;
            }

            // Parse the PHP file and extract the test method names.
            $code = file_get_contents($filePath);
            if (false === $code) {
                // The file is not readable, skip it.
                continue;
            }

            $ast = $parser->parse($code);
            if (is_null($ast)) {
                // The file could not be parsed, skip it.
                continue;
            }

            $traverser = new NodeTraverser();
            $visitor = new TestMethodRecorderVisitor();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            $methods = $visitor->getMethods();

            foreach ($methods as $fqdn => $testLines) {
                foreach ($lines as $changedRange) {
                    if ($this->rangeIntercepts($testLines, $changedRange)) {
                        $tests[] = $fqdn;
                    }
                }
            }
        }

        return $tests;
    }

    /**
     * @param array{0:int, 1:int} $range1
     * @param array{0:int, 1:int} $range2
     */
    protected function rangeIntercepts(array $range1, array $range2): bool
    {
        // Ensure the ranges values are in ascending order.
        if ($range1[0] > $range1[1]) {
            $range1 = [
                $range1[1],
                $range1[0]
            ];
        }
        if ($range2[0] > $range2[1]) {
            $range2 = [
                $range2[1],
                $range2[0]
            ];
        }

        // Does either range start or end within the other range?
        if (
            $this->isNumberInRange($range1[0], $range2)
            || $this->isNumberInRange($range1[1], $range2)
            || $this->isNumberInRange($range2[0], $range1)
            || $this->isNumberInRange($range2[1], $range1)
        ) {
            return true;
        }

        return false;
    }
}
