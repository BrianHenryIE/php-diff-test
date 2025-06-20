<?php

namespace BrianHenryIE\PhpDiffTest;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
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

    public function execute(array $coverageFilePaths, string $diffFrom, string $diffTo, Granularity $granularity = Granularity::LINE): string
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

        $diffFilesLineRanges = $this->diffLines->getChangedLines(
            $diffFrom,
            $diffTo,
            function (string $filePath): bool {
                    return substr($filePath, -4) === '.php';
            }
        );

        $fqdnDiffTests = $this->getDiffTests($diffFilesLineRanges);

        $fqdnTestsToRunBySuite = $this->getFqdnTestsToRunBySuite(
            $coverageSuiteNamesFilePaths,
            $diffFilesLineRanges,
            $granularity,
        );

        if ($this->isCodeceptionRun($this->cwd, array_keys($coverageSuiteNamesFilePaths))) {
            // codecept run wpunit ":API_WPUnit_Test:test_add_autologin_to_message"
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
                    // Tests with data providers are indexed by test_name#dataprovider which has caused problems,
                    // so let's just run those tests with all the values
                    fn($testName) => preg_split('/#/', $testName)[0] ?: $testName,
                    // array_values here just to make it easier to read the output.
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
     * @param Granularity $granularity Return tests which cover the specific lines, or cover anywhere in the modified file.
     * @return array<string, string[]> array of arrays, indexed by suitename, of FQDN test cases
     */
    protected function getFqdnTestsToRunBySuite(array $coverageSuiteNamesFilePaths, array $diffFilesLineRanges, Granularity $granularity): array
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
                                $fqdnTestsToRunBySuite[$suiteName][$test] = $test;
                            }
                            break;
                        case Granularity::LINE:
                            if ($this->isNumberInRanges($lineNumber, $diffFilesLineRanges[$srcAbspath])) {
                                /**
                                 * @var string $test is the FQDN string of the test for this line number
                                 */
                                foreach ($tests as $test) {
                                    $fqdnTestsToRunBySuite[$suiteName][$test] = $test;
                                }
                            }
                            break;
                    }
                }
            }
        }
        return $fqdnTestsToRunBySuite;
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
        // TODO: check the numbers are in order.
        return $number >= $range[0] && $number <= $range[1];
    }

    private function fqdnToCodeceptionFriendlyShortname(string $test): string
    {
        list( $testFqdnClassName, $testMethod ) = explode('::', $test);
        $parts = explode('\\', $testFqdnClassName);
        $shortClass = array_pop($parts);
        return $shortClass . ':' . $testMethod;
    }

    /**
     * $param array<string, array<int[]>> $diffLinesPhp Index: filepath, array of pairs (ranges) of changed lines, already filtered to .php files.
     * @return array<string> FQDN test cases
     */
    private function getDiffTests(array $diffLinesPhp): array
    {
        $testFilesLines = array_filter(
            $diffLinesPhp,
            function (string $filePath): bool {
                return substr($filePath, -8) === 'Test.php';
            },
            ARRAY_FILTER_USE_KEY
        );

        $tests = [];

        foreach ($testFilesLines as $filePath => $lines) {
            if (!is_readable($filePath)) {
                // The codecoverage report is out of sync with the branch. E.g. it was generated on a different branch.
                continue;
            }


            // Parse the PHP file and extract the test method names.

            $code = file_get_contents($filePath);

            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

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
