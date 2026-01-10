<?php

/**
 * Playing with the Gitonomy library.
 */

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use Gitonomy\Git\Repository;

/**
 * @coversDefaultClass \BrianHenryIE\PhpDiffTest\DiffLines
 */
final class DiffLinesTest extends IntegrationTestCase
{
    //fatal: not a git repository (or any of the parent directories): .git

    public function testGetChangedFiles(): void
    {

        $this->shellExec('git init');
        $repository = new Repository($this->testsWorkingDir);
//        $repository->run('init');

        file_put_contents($this->testsWorkingDir . 'file1.txt', "file1-contents\n\n");
        $repository->run('add', ['file1.txt']);
        file_put_contents($this->testsWorkingDir . 'file2.txt', "file2-contents\n\n");
        $repository->run('add', ['file2.txt']);
        file_put_contents($this->testsWorkingDir . 'file3.txt', "file3-contents\n\n");
        $repository->run('add', ['file3.txt']);
        $repository->run('commit', [ '-am', 'Initial commit']);

        $repository->getReferences()->createBranch(
            'new-branch',
            $repository->getHead()?->getCommitHash()
        );

        // Add and commit a new file.
        file_put_contents($this->testsWorkingDir . 'file4.txt', "file4-contents\n\n");
        $repository->run('add', ['file4.txt']);
        file_put_contents($this->testsWorkingDir . 'file5.txt', "file5-contents\n\n");
        $repository->run('add', ['file5.txt']);
        $repository->run('commit', ['-am', 'Commit 2']);

        // Modify and add an existing file.
        file_put_contents($this->testsWorkingDir . 'file2.txt', "file2-contents\n\n\n\nmodified");
        $repository->run('add', ['file2.txt']);

        // Modify an existing file but do not add.
        file_put_contents($this->testsWorkingDir . 'file3.txt', "file3-contents\n\n\n\nmodified");
        file_put_contents($this->testsWorkingDir . 'file5.txt', "file5-contents\n\n\n\nmodified");

        // Create a new file but do not add.
        file_put_contents($this->testsWorkingDir . 'file6.txt', "file6-contents\n\n");

        $staged = $repository->getWorkingCopy()->getDiffStaged();
        $pending = $repository->getWorkingCopy()->getDiffPending();
        $untrackedFiles = $repository->getWorkingCopy()->getUntrackedFiles();

        // Contains only the commited changes.
//        $diff = $repository->getDiff('main..HEAD~0');
        $diff = $repository->getDiff("main..{$repository->getHead()?->getCommitHash()}");

        $changes = array_merge(
            $diff->getFiles(), // Should be file4.txt, file5.txt (\Gitonomy\Git\Diff\File)
            $staged->getFiles(), // Should be file2.txt (\Gitonomy\Git\Diff\File)
            $pending->getFiles(), // Should be file3.txt, file5.txt (\Gitonomy\Git\Diff\File)
            $untrackedFiles, // Should be file6.txt (string)
        );


        $fileLineChangesRanges = [];

        foreach ($changes as $file) {
            if ($file instanceof \Gitonomy\Git\Diff\File) {
                if (!isset($fileLineChangesRanges[$file->getName()])) {
                    $fileLineChangesRanges[$file->getName()] = [];
                }
                foreach ($file->getChanges() as $fileChange) {
                    $fileLineChangesRanges[$file->getName()][] = [
                        $fileChange->getRangeNewStart(),
                        $fileChange->getRangeNewStart() + $fileChange->getRangeNewCount()
                    ];
                }
            } else {
                $fileLineChangesRanges[$file] = [[0,0]];
            }
        }

        // New files.
        foreach ($fileLineChangesRanges as $filename => $lineRanges) {
            foreach ($lineRanges as $range) {
                if ($range[0] === 0 && $range[1] === 0) {
                    $fileLineChangesRanges[$filename] = [[ 0, $this->getNumberOfLinesInAFile($this->testsWorkingDir . $filename) ]];
                }
            }
        }

        // main..HEAD~0
        $difflines = new DiffLines($this->testsWorkingDir);
        $result = $difflines->getChangedLines();

        self::markTestIncomplete();
    }

    protected function getNumberOfLinesInAFile(string $path): int
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key() - 1;
    }
}
