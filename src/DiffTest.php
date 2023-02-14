<?php

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use ReflectionClass;
use SebastianBergmann\CodeCoverage\CodeCoverage;

class DiffTest
{
    public function run($projectRootDir)
    {

        // Search $projectRootDir and two levels of tests for *.cov
        // If there are corresponding *.suite.yml, treat them as Codeception
        // otherwise treat them as PhpUnit.

        $covFiles = array_merge(
            glob($projectRootDir . '/*.cov'),
            glob($projectRootDir . '/tests/*.cov'),
            glob($projectRootDir . '/tests/*/*.cov')
        );
        $covNamesPaths = array();
        foreach ($covFiles as $filepath) {
            preg_match('/.*\/(.*).cov/', $filepath, $output_array);
            $name = $output_array[1];
            $covNamesPaths[$name] = $filepath;
        }

        $codeceptionSuites = array();
        $codeceptionSuitesFiles = glob($projectRootDir . '/tests/*.suite.y*ml');
        foreach ($codeceptionSuitesFiles as $filepath) {
            preg_match('/.*\/(.*)\.suite\.y.?ml/', $filepath, $output_array);
            $name = $output_array[1];
            $codeceptionSuites[$name] = $filepath;
        }

        $changedFiles = $this->getChangedFiles($projectRootDir);
        $filesLines   = $this->getChangedLinesPerFile($projectRootDir, $changedFiles);

        foreach ($codeceptionSuites as $suiteName => $suiteFilepath) {
            if (! isset($covNamesPaths[$suiteName])) {
                continue;
            }

            /** @var CodeCoverage $coverage */
            $coverage = include $covNamesPaths[$suiteName];
            unset($covNamesPaths[$suiteName]);

            $srcFilesAbsolutePaths = array_keys($filesLines);

            $fqdnTestClassesAndMethods   = array();
            $fqdnTestClassesAndFilepaths = array();
            $fqdnTestClassesAndShortname = array();

            foreach ($srcFilesAbsolutePaths as $srcAbspath) {
                $lineCoverage = $coverage->getData()->lineCoverage();
                if (! isset($lineCoverage[ $srcAbspath ])) {
                    continue;
                }
                foreach ($lineCoverage[ $srcAbspath ] as $lineNumber => $tests) {
                    if (in_array($lineNumber, $filesLines[ $srcAbspath ])) {
                        foreach ($tests as $test) {
                            list( $testFqdnClassName, $testMethod ) = explode('::', $test);
                            if (! isset($fqdnTestClassesAndMethods[$testFqdnClassName])) {
                                $fqdnTestClassesAndMethods[$testFqdnClassName] = array();
                            }
                            $fqdnTestClassesAndMethods[$testFqdnClassName][] = $testMethod;

                            // TODO: Catch this: dump-autoload probably needs to be run.
                            $reflectedClass                                    = new ReflectionClass($testFqdnClassName);
                            $fqdnTestClassesAndFilepaths[ $testFqdnClassName ] = $reflectedClass->getFileName();
                            $fqdnTestClassesAndShortname[$testFqdnClassName] = $reflectedClass->getShortName();
                        }
                    }
                }
            }

            if (empty($fqdnTestClassesAndShortname) || empty($fqdnTestClassesAndMethods)) {
                continue;
            }

            $a = array_values($fqdnTestClassesAndShortname);
            $b = array_unique(array_merge(...array_values($fqdnTestClassesAndMethods)));

            $cmd = "cd $projectRootDir && vendor/bin/codecept run $suiteName "
                   . '":' . implode('|', array_values($fqdnTestClassesAndShortname))
                   . ':' . implode('|', array_unique(array_merge(...array_values($fqdnTestClassesAndMethods)))) . '"';

            passthru("cd $projectRootDir && " . $cmd);
        }

        foreach ($covNamesPaths as $covPath) {
            // TODO phpunit
        }
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
}
