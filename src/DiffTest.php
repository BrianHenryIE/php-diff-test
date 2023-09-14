<?php

namespace BrianHenryIE\PhpDiffTest;

use Exception;
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
            preg_match('/.*\/(.*).cov/', $filePath, $outputArray);
            $name = $outputArray[1];
            $coverageSuiteNamesFilePaths[$name] = $filePath;
        }

        $changedFilesAll = $this->getChangedFiles($projectRootDir);
        $changedFiles = array_filter($changedFilesAll, function (string $filePath): bool {
            return substr($filePath, -4) === '.php';
        });
        $diffFilesLines   = $this->getChangedLinesPerFile($projectRootDir, $changedFiles);

        $fqdnTestsToRunBySuite = array();

        foreach ($coverageSuiteNamesFilePaths as $suiteName => $coverageFilePath) {
            $fqdnTestsToRunBySuite[$suiteName] = array();

            /** @var CodeCoverage $coverage */
            $coverage = include $coverageFilePath;

            $srcFilesAbsolutePaths = array_keys($diffFilesLines);

            $fqdnTestClassesAndMethods   = array();
            $fqdnTestClassesAndFilepaths = array();
            $fqdnTestClassesAndShortname = array();

            $lineCoverage = $coverage->getData()->lineCoverage();
            foreach ($srcFilesAbsolutePaths as $srcAbspath) {
                if (! isset($lineCoverage[ $srcAbspath ])) {
                    continue;
                }
                foreach ($lineCoverage[ $srcAbspath ] as $lineNumber => $tests) {
                    if (in_array($lineNumber, $diffFilesLines[ $srcAbspath ])) {
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

    private function isCodeceptionRun($projectRootDir, $coverageSuiteNames)
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

    private function fqdnToCodeceptionFriendlyShortname(string $test)
    {
        list( $testFqdnClassName, $testMethod ) = explode('::', $test);
        $parts = explode('\\', $testFqdnClassName);
        $shortClass = array_pop($parts);
        return $shortClass . ':' . $testMethod;
    }

    /**
     * Returns a list of files which are within the diff based on the current branch
     *
     * Get a list of changed files (not including deleted files)
     *
     * @see https://github.com/olivertappin/phpcs-diff/blob/master/src/PhpcsDiff.php
     *
     * @return string[] List of absolute filepaths for files known to exist.
     * @throws Exception When `shell_exec()` fails.
     */
    private function getChangedFiles($dir): array
    {
        $cmd = 'cd ' . $dir . ' && git diff --name-only --diff-filter=ACM';

        $shellOutput = shell_exec($cmd);

        if (empty($shellOutput)) {
            throw new Exception("shell_exec failed running `$cmd`.");
        }

        /**
         * Convert files into an array.
         *
         * @var string[] $output
         */
        $relativeFilepaths = explode(PHP_EOL, $shellOutput);

        // Prepend $dir to get absolute path.
        $absoluteFilepaths = array_map(function ($path) use ($dir): string {
            return $dir . '/' . $path;
        }, $relativeFilepaths);

        // Remove any invalid values.
        return array_filter($absoluteFilepaths, function (string $maybeFile): bool {
            return file_exists($maybeFile);
        });
    }

    /**
     * Extract the changed lines for each file from the git diff output
     *
     * @see https://github.com/olivertappin/phpcs-diff/blob/master/src/PhpcsDiff.php
     *
     * @param string[] $files
     *
     * @return array<string, int[]>
     */
    private function getChangedLinesPerFile(string $dir, array $files): array
    {
        $extract = [];
        $pattern = [
            'basic'    => '^@@ (.*) @@',
            'specific' => '@@ -[0-9]+(?:,[0-9]+)? \+([0-9]+)(?:,([0-9]+))? @@',
        ];

        foreach ($files as $file) {
            $command = 'cd ' . $dir . ' && git diff -U0 ' . $file .
                       ' | grep -E ' . escapeshellarg($pattern['basic']);

            $lineDiff     = shell_exec($command);
            $lines        = array_filter(explode(PHP_EOL, $lineDiff));
            $linesChanged = [];

            foreach ($lines as $line) {
                preg_match('/' . $pattern['specific'] . '/', $line, $matches);

                // If there were no specific matches, skip this line
                if ([] === $matches) {
                    continue;
                }

                $start = $end = (int) $matches[1];

                // Multiple lines were changed, so we need to calculate the end line
                if (isset($matches[2])) {
                    $length = (int) $matches[2];
                    $end    = $start + $length - 1;
                }

                foreach (range($start, $end) as $l) {
                    $linesChanged[ $l ] = null;
                }
            }

            $extract[ $file ] = array_keys($linesChanged);
        }

        return $extract;
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
