<?php

/**
 * Base test case that creates and deletes a temporary working directory
 */

namespace BrianHenryIE\PhpDiffTest;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class IntegrationTestCase extends TestCase
{
    protected string $testsWorkingDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->testsWorkingDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'php-diff-test' . DIRECTORY_SEPARATOR;

        if ('Darwin' === PHP_OS) {
            $this->testsWorkingDir = DIRECTORY_SEPARATOR . 'private' . $this->testsWorkingDir;
        }

        if (file_exists($this->testsWorkingDir)) {
            $this->deleteDir($this->testsWorkingDir);
        }

        @mkdir($this->testsWorkingDir);
    }

    /**
     * Delete $this->testsWorkingDir after each test.
     *
     * @see https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

        $this->deleteDir($dir);
    }

    protected function deleteDir($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if (is_link($file)) {
                unlink($file);
            } elseif ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }


    private function shellExec(string $cmd)
    {
        $cmd = 'cd ' . $this->testsWorkingDir . ' && ' . $cmd;
        $shellOutput = shell_exec($cmd);
//        if (empty($shellOutput) && ! str_contains($cmd, 'git add')) {
//            throw new Exception("shell_exec failed running `$cmd`.");
//        }
        return $shellOutput;
    }

    public function shell($command, array $env = [])
    {

//        $newpath = getenv('PATH');
//
//        $command = "putenv(\"PATH=$newpath\"); $command";
//        $command = "\PATH=\"$newpath\"; $command";

        /** @var resource|false $resource */
        $resource = proc_open(
            escapeshellarg($command),
            [STDIN, STDOUT, STDERR],
            $pipes,
            $this->testsWorkingDir
        );

        proc_close($resource);
    }

}
