<?php

/**
 * Filters a code coverage file to include only the files contained in a diff.
 *
 * @see \BrianHenryIE\PhpDiffTest\DiffCoverage
 */

namespace BrianHenryIE\PhpDiffTest;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Configures the Symfony console command.
 * Parses the CLI arguments / defaults, ensures the files are readable, and calls the DiffCoverage class.
 */
class DiffCoverageCLI extends Command
{
    /**
     * Tool which merges coverage files, determines files in diff, and filters and writes coverage.
     */
    protected DiffCoverage $diffCoverage;

    /**
     * Current working directory, with trailing slash.
     *
     * @see getcwd()
     */
    protected string $cwd;

    /**
     * @param ?string $name Symfony parameter.
     * @param ?DiffCoverage $diffCoverage
     * @throws \Exception
     */
    public function __construct(
        ?string $name = null,
        ?DiffCoverage $diffCoverage = null,
    ) {
        parent::__construct($name);

        $this->initCwd();

        $this->diffCoverage = $diffCoverage ?? new DiffCoverage($this->cwd);
    }

    /**
     * Ensure we have the object property for the working directory, with trailing slash.
     *
     * @used-by DiffCoverage::__construct()
     * @see getcwd()
     */
    protected function initCwd(): void
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new \Exception('Could not get current working directory.');
        }

        $this->cwd = rtrim($cwd, '/\\') . '/';
    }

    /**
     * @used-by Command::run()
     * @see Command::configure()
     */
    protected function configure()
    {
        $this->setName('diffcoverage');
        $this->setDescription('Filter a code coverage file to only include the files contained in a diff.');

        $this->addOption(
            'input-files',
            null,
            InputArgument::OPTIONAL,
            'Comma-separated list of PHP code coverage files to filter.',
        );

         $this->addOption(
             'diff-from',
             null,
             InputArgument::OPTIONAL,
             'Reference to diff from.',
         );

         $this->addOption(
             'diff-to',
             null,
             InputArgument::OPTIONAL,
             'Reference to diff from.',
         );

        /**
         * Output file should not be in a directory that contains other php coverage files or `phpcov` will merge all
         * of them, including in subdirectories. So this defaults to `./diff-coverage/diff-{$diffFrom}-{$diffTo}.cov`
         * in the current working directory.
         *
         * When scripting this, it's probably best to clear the directory each time.
         */
        $this->addOption(
            'output-file',
            null,
            InputArgument::OPTIONAL,
            'Output file path.',
        );
    }

    /**
     * @used-by Command::run()
     * @see Command::execute()
     *
     * @param InputInterface $input {@see ArgvInput}
     * @param OutputInterface $output
     *
     * @return int Shell exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFiles = $input->getOption('input-files')
            ? explode(',', $input->getOption('input-files'))
            : $this->getCodeCoverageFilepaths($this->cwd);
        $diffFrom = $input->getOption('diff-from') ?? 'main';
        $diffTo = $input->getOption('diff-to') ?? 'HEAD^';
        $outputFile = $input->getOption('output-file') ?? "diff-coverage/diff-{$diffFrom}-{$diffTo}.cov";

        if (empty($inputFiles)) {
            $output->writeln('No code coverage files found.');
            return Command::FAILURE;
        }

        // Ensure the files are readable.
        $coverageFilePaths = array_map(function (string $inputFile): string {
            $path = str_starts_with($inputFile, $this->cwd) ? $inputFile : $this->cwd . $inputFile;
            if (!is_readable($path)) {
                throw new \Exception("File not found or not readable: $path");
            }
            return $path;
        }, $inputFiles);

        try {
            $this->diffCoverage->execute($coverageFilePaths, $diffFrom, $diffTo, $outputFile);
            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * Search $projectRootDir and two levels of tests for *.cov
     *
     * @param string $projectRootDir As earlier determined from `getcwd()`.
     *
     * @return string[]
     */
    protected function getCodeCoverageFilepaths(string $projectRootDir): array
    {
        return array_filter(array_merge(
            glob($projectRootDir . '/*.cov') ?: [],
            glob($projectRootDir . '/tests/*.cov') ?: [],
            glob($projectRootDir . '/tests/*/*.cov') ?: [],
        ));
    }
}
