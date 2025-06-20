<?php

/**
 * Outputs a markdown report of the code coverage.
 *
 * Intended for GitHub Actions to output the code coverage in a PR comment.
 *
 * @see \BrianHenryIE\PhpDiffTest\DiffCoverage
 */

namespace BrianHenryIE\PhpDiffTest;

use Exception;
use BrianHenryIE\PhpDiffTest\MarkdownReport\MarkdownReport;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MarkdownReportCLI extends Command
{
    protected MarkdownReport $markdownReport;

    /**
     * Current working directory, with trailing slash.
     *
     * @see getcwd()
     */
    protected string $cwd;

    /**
     * @param ?string $name Symfony parameter.
     * @param ?MarkdownReport $markdownReport
     * @throws Exception
     */
    public function __construct(
        ?string $name = null,
        ?MarkdownReport $markdownReport = null,
    ) {
        parent::__construct($name);

        $this->markdownReport = $markdownReport ?? new MarkdownReport();
    }

    /**
     * @used-by Command::run()
     * @see Command::configure()
     * @return void
     */
    protected function configure()
    {
        $this->setName('markdown-report');
        $this->setDescription('Output a markdown report of the code coverage.');

        $this->addOption(
            'input-file',
            null,
            InputArgument::OPTIONAL,
            'Path to a .cov PHP code coverage file.',
        );

        // To link to the file in a PR at the last commit:
        // https://github.com/<company/project>/blob/<sha>/<path/to/file.php>
         $this->addOption(
             'base-url',
             null,
             InputArgument::OPTIONAL,
             'URL where the HTML report is hosted.',
         );

         // or null to output to stdout
        $this->addOption(
            'output-file',
            null,
            InputArgument::OPTIONAL,
            'Output file path.',
        );

         // or absent to include all files in the report
        $this->addOption(
            'covered-files',
            null,
            InputArgument::OPTIONAL,
            'List of files to include in the report.',
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
        $coverageFilePath = $input->getOption('input-file');
        $baseUrl = $input->getOption('base-url');
        $outputFile = $input->getOption('output-file');
        $coveredFilesList = $input->getOption('covered-files') ?: '';

        if (!is_readable($coverageFilePath)) {
            $output->writeln('Unable to read coverage file: ' . $coverageFilePath);
            return Command::FAILURE;
        }

        /** @var CodeCoverage $coverage */
        $coverage = include $coverageFilePath;
        try {
            $coverage = include $coverageFilePath;
        } catch (Exception $exception) {
            $output->writeln(
                sprintf(
                    "Coverage file: %s probably created with an incompatible PHPUnit version.",
                    $coverageFilePath
                )
            );
            return Command::FAILURE;
        }

        // At this point, we don't care that the files exits or not. We will check str_ends_with() to filter later.
        $coveredFilesList = array_map('trim', explode(',', $coveredFilesList));

        try {
            $projectRoot = getcwd() . '/';
            $result = $this->markdownReport->process($coverage, $projectRoot, $baseUrl, $coveredFilesList);
            if ($outputFile) {
                file_put_contents(
                    $outputFile,
                    $result
                );
            } else {
                $output->write($result);
            }

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }
    }
}
