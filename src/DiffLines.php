<?php
/**
 *
 * Consider {@see https://github.com/sebastianbergmann/diff} which is used by PHPUnit so already part of the project.
 */

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use Gitonomy\Git\Repository;

class DiffLines
{
    /**
     * @param string $projectRootDir Path to the Git repository.
     * @param ?string $diff Branch or commit hash to diff against.
     * @return array<string, array<int[]>>
     * @throws Exception
     */
    public function getChangedLines(string $projectRootDir, ?string $diffFrom = 'main', ?string $diffTo = null): array
    {
        $projectRootDir = rtrim($projectRootDir, '/') . '/';

        $changedFilesAll = $this->getChangedFiles($projectRootDir);
        $diffFilesLines   = $this->getChangedLinesForFiles($projectRootDir, $changedFilesAll);

        $diffPhpFilesLines = array_filter($diffFilesLines, function (string $filePath): bool {
            return substr($filePath, -4) === '.php';
        }, ARRAY_FILTER_USE_KEY);

        return $diffPhpFilesLines;
    }

    /**
     * Extract the changed lines for each file from the git diff output
     *
     * @param array<string|\Gitonomy\Git\Diff\File> $files
     *
     * @return array<string, array<int[]>> Index: filename, array of pairs of changed lines.
     */
    protected function getChangedLinesForFiles(string $projectRootDir, array $files): array
    {

        $fileLineChangesRanges = [];

        foreach ($files as $file) {
            $pathIndex = $file instanceof \Gitonomy\Git\Diff\File
                ? $projectRootDir . $file->getName()
                : $projectRootDir . $file;

            if (!isset($fileLineChangesRanges[$pathIndex])) {
                $fileLineChangesRanges[$pathIndex] = [];
            }

            $fileLineChangesRanges[$pathIndex] = $file instanceof \Gitonomy\Git\Diff\File
                ? array_merge(
                    $fileLineChangesRanges[$pathIndex],
                    $this->getChangedLinesPerFile($file, $pathIndex)
                )
                : [[ 0, $this->getNumberOfLinesInAFile($pathIndex) ]];
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
     * @param \Gitonomy\Git\Diff\File $file
     * @param string $pathIndex The full file path.
     * @return array<int[]>
     */
    protected function getChangedLinesPerFile(\Gitonomy\Git\Diff\File $file, string $pathIndex): array
    {
        $fileLineChangesRanges = [];
        foreach ($file->getChanges() as $fileChange) {
            // New file.
            if ($fileChange->getRangeOldStart() === 0 && $fileChange->getRangeOldCount() === 0) {
                $fileLineChangesRanges = [];
                $fileLineChangesRanges[] = [[ 0, $this->getNumberOfLinesInAFile($pathIndex) ]];
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
     * Returns a list of files which are within the diff based on the current branch
     *
     * Get a list of changed files (not including deleted files)
     *
     * @param string $projectRootDir
     * @param string|null $diff
     * @return array<string|\Gitonomy\Git\Diff\File>
     */
    protected function getChangedFiles(string $projectRootDir, ?string $diff = 'main'): array
    {
        $repository = new Repository($projectRootDir);

        $staged = $repository->getWorkingCopy()->getDiffStaged();
        $pending = $repository->getWorkingCopy()->getDiffPending();
        $untrackedFiles = $repository->getWorkingCopy()->getUntrackedFiles();

        // Contains only the commited changes.
        $diff = $repository->getDiff("$diff..HEAD^");

        $changes = array_merge(
            $diff->getFiles(),
            $staged->getFiles(),
            $pending->getFiles(),
            $untrackedFiles,
        );

        return $changes;
    }


    protected function getNumberOfLinesInAFile(string $path): int
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key() - 1;
    }
}
