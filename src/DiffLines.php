<?php

/**
 * Given two commits or branches, returns an array of changed lines ranges in each file.
 *
 * [filepath => [[start, end], ...]
 *
 * Uses {see https://github.com/gitonomy/gitlib}.
 * Consider {@see https://github.com/sebastianbergmann/diff} which is used by PHPUnit so already part of the project.
 */

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use Gitonomy\Git\Diff\File;
use Gitonomy\Git\Repository;
use SplFileObject;

class DiffLines
{
    /** @var string $cwd */
    protected $cwd;

    /** @var Repository $repository */
    protected $repository;

    public function __construct(
        string $cwd, // With trailing slash.
        ?Repository $repository = null,
    ) {
        $this->cwd = $cwd;
        $this->repository = $repository ?? new Repository($cwd);
    }

    /**
     * @param string $diffFrom Branch or commit hash to diff against.
     * @param string $diffTo Branch or commit hash to diff against.
     * @param ?callable $filePathFilter Optional filter function to apply to file paths.
     * @return array<string, array<array{0:int, 1:int}>> Index: filepath, array of pairs (ranges) of changed lines.
     * @throws Exception
     */
    public function getChangedLines(
        string $diffFrom = 'main',
        string $diffTo = 'HEAD~0',
        ?callable $filePathFilter = null
    ): array {

        $changedFilesAll = $this->getChangedFiles($this->repository, $diffFrom, $diffTo);

        // A `$changedFile` will be a string for untracked files, or an instance of `File` for tracked files.
        $removeDeleteFilesFilter = fn ($changedFile) => !($changedFile instanceof File) || !$changedFile->isDeletion();

        // Remove deleted files from the list.
        $changedFiles = array_filter($changedFilesAll, $removeDeleteFilesFilter);

        $diffFilesLines = $this->getChangedLinesForFiles($this->cwd, $changedFiles);

        return $filePathFilter
            ? array_filter($diffFilesLines, $filePathFilter, ARRAY_FILTER_USE_KEY)
            : $diffFilesLines;
    }

    /**
     * Extract the changed lines for each file from the git diff output
     *
     * @param array<string|File> $files
     *
     * @return array<string, array<array{0:int,1:int}>> Index: filename, array of pairs of changed lines.
     */
    protected function getChangedLinesForFiles(string $projectRootDir, array $files): array
    {

        $fileLineChangesRanges = [];

        foreach ($files as $file) {
            $pathIndex = $file instanceof File
                ? $projectRootDir . $file->getName()
                : $projectRootDir . $file;

            if (!isset($fileLineChangesRanges[$pathIndex])) {
                $fileLineChangesRanges[$pathIndex] = [];
            }

            $fileLineChangesRanges[$pathIndex] = array_merge(
                $fileLineChangesRanges[$pathIndex],
                $file instanceof File
                                ? $this->getChangedLinesPerFile($file, $pathIndex)
                                : [[ 0, $this->getNumberOfLinesInAFile($pathIndex) ]]
            );
        }

        // New files.
        foreach ($fileLineChangesRanges as $pathIndex => $lineRanges) {
            foreach ($lineRanges as $range) {
                if ($range[0] === 0 && $range[1] === 0) {
                    $fileLineChangesRanges[$pathIndex] = [[ 0, $this->getNumberOfLinesInAFile($pathIndex) ]];
                    break;
                }
            }
        }

        return $fileLineChangesRanges;
    }

    /**
     * @param File $file
     * @param string $pathIndex The full file path.
     * @return array<int[]>
     */
    protected function getChangedLinesPerFile(File $file, string $pathIndex): array
    {
        $fileLineChangesRanges = [];
        foreach ($file->getChanges() as $fileChange) {
            // New file.
            if ($fileChange->getRangeOldStart() === 0 && $fileChange->getRangeOldCount() === 0) {
                $fileLineChangesRanges = [];
                $fileLineChangesRanges[] = [ 0, $this->getNumberOfLinesInAFile($pathIndex) ];
                return $fileLineChangesRanges;
            }

            // Diffs show the leading and following three lines around those that are actually changed.
            $fileLineChangesRanges[] = [
                $fileChange->getRangeNewStart() + 3,
                $fileChange->getRangeNewStart() + $fileChange->getRangeNewCount() - 3
            ];
        }
        return $fileLineChangesRanges;
    }

    /**
     * Returns a list of all files which are within the diff of the two references.
     *
     * Includes untracked, pending, staged files. Includes deleted files.
     *
     * TODO: If $diffTo is not HEAD~0, we probably shouldn't included untracked, pending, staged files.
     *
     * @param Repository $repository
     * @param string $diffFrom
     * @param string $diffTo
     * @return array<string|File>
     */
    protected function getChangedFiles(
        Repository $repository,
        string $diffFrom = 'main',
        string $diffTo = 'HEAD~0'
    ): array {

        $staged = $repository->getWorkingCopy()->getDiffStaged();
        $pending = $repository->getWorkingCopy()->getDiffPending();
        $untrackedFiles = $repository->getWorkingCopy()->getUntrackedFiles();

        // Contains only the commited changes.
        $diff = $repository->getDiff("$diffFrom..$diffTo");

        return array_merge(
            $diff->getFiles(),
            $staged->getFiles(),
            $pending->getFiles(),
            $untrackedFiles,
        );
    }

    /**
     * Untracked files are not part of the diff, so we need to count the lines in the file to say the range is
     * [0, numberOfLinesInFile].
     */
    protected function getNumberOfLinesInAFile(string $path): int
    {
        if (!is_readable($path)) {
            return PHP_INT_MAX;
        }
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key() - 1;
    }
}
