<?php

/**
 * Prints a PHPUnit or Codeception filter to run only the tests which cover the lines in a diff.
 *
 * @see \BrianHenryIE\PhpDiffTest\DiffCoverage
 */

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Configures the Symfony console command.
 * Parses the CLI arguments / defaults, ensures the files are readable, and calls the DiffFilter class.
 */
class DiffFilterCLI extends Command
{
    /**
     * Tool which merges coverage files, determines lines in diff, calculates tests which cover those lines,
     * and prints out a PHPUnit filter.
     */
    protected DiffFilter $diffFilter;

    /**
     * Current working directory, with trailing slash.
     *
     * @see getcwd()
     */
    protected string $cwd;

    /**
     * @param ?string $name Symfony parameter.
     * @param ?DiffFilter $diffFilter
     * @throws Exception
     */
    public function __construct(
        ?string $name = 'difffilter',
        ?DiffFilter $diffFilter = null,
    ) {
        parent::__construct($name);

        $this->initCwd();

        $this->diffFilter = $diffFilter ?? new DiffFilter($this->cwd);
    }

    /**
     * Ensure we have the object property for the working directory, with trailing slash.
     *
     * @used-by DiffCoverage::__construct()
     * @see     getcwd()
     * @throws Exception
     */
    protected function initCwd(): void
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new Exception('Could not get current working directory.');
        }

        $this->cwd = rtrim($cwd, '/\\') . '/';
    }

    /**
     * @used-by Command::run()
     * @see Command::configure()
     * @return void
     */
    protected function configure()
    {
        $this->setName('filter');
        $this->setDescription('Create a PHPUnit filter to run tests only on the lines/files contained in a diff.');

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
             'Reference to diff to.',
         );

         $this->addOption(
             'granularity',
             null,
             InputArgument::OPTIONAL,
             '`line`|`file`. Return test cases that cover anywhere in files in the diff or specific to lines changed in the diff',
         );
    }

    /**
     * @used-by Command::run()
     * @param InputInterface $input {@see ArgvInput}
     * @param OutputInterface $output
     *
     * @return int Shell exit code.
     * @throws Exception
     * @see     Command::execute()
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFiles = $input->getOption('input-files')
            ? explode(',', $input->getOption('input-files'))
            : $this->getCodeCoverageFilepaths($this->cwd);
        $diffFrom = $input->getOption('diff-from') ?? 'main';
        $diffTo = $input->getOption('diff-to') ?? 'HEAD~0';
        $granularity = Granularity::from($input->getOption('granularity') ?? 'line');

        if (empty($inputFiles)) {
            $output->writeln('No code coverage files found.');
            return Command::FAILURE;
        }

        // Ensure the files are readable.
        $coverageFilePaths = array_map(function (string $inputFile): string {
            $path = str_starts_with($inputFile, $this->cwd) ? $inputFile : $this->cwd . $inputFile;
            if (!is_readable($path)) {
                throw new Exception("File not found or not readable: $path");
            }
            return $path;
        }, $inputFiles);

        try {
            $result = $this->diffFilter->execute($coverageFilePaths, $diffFrom, $diffTo, $granularity);
            $output->write($result);
            return Command::SUCCESS;
        } catch (Exception $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * Search $projectRootDir and two levels of tests for *.cov
     *
     * If there are corresponding *.suite.yml, treat them as Codeception
     * otherwise treat them as PhpUnit.
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
