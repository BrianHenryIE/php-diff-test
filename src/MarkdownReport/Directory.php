<?php

declare(strict_types=1);

/*
 * @see phpunit/php-code-coverage.
 * @see \SebastianBergmann\CodeCoverage\Report\Html\Directory
 */

namespace BrianHenryIE\PhpDiffTest\MarkdownReport;

use SebastianBergmann\CodeCoverage\Report\Html\Renderer;
use SebastianBergmann\CodeCoverage\Node\AbstractNode as Node;
use SebastianBergmann\CodeCoverage\Node\Directory as DirectoryNode;
use SebastianBergmann\CodeCoverage\Report\Thresholds;
use SebastianBergmann\Template\Template;

use function sprintf;
use function str_repeat;

class Directory extends Renderer
{
    public function __construct(
        protected string $basePath, // The directory to remove from the beginning of each path
        protected ?string $baseUrl, // The URL to prefix to each path
        string $templatePath,
        string $generator,
        string $date,
        Thresholds $thresholds,
        bool $hasBranchCoverage
    ) {
        parent::__construct($templatePath, $generator, $date, $thresholds, $hasBranchCoverage);
    }

    protected function flatten(DirectoryNode $node): array
    {
        $items = [];

        foreach ($node->directories() as $item) {
            $items = array_merge($items, $this->flatten($item));
        }

        foreach ($node->files() as $item) {
            $items[] = $item;
        }

        return $items;
    }

    public function render(DirectoryNode $node): string
    {
        $templateName = $this->templatePath . ($this->hasBranchCoverage ? 'directory_branch.html' : 'directory.html');
        $template     = new Template($templateName, '{{', '}}');

        $this->setCommonTemplateVariables($template, $node);

        $items = $this->renderItem($node, true);

        $allFiles = $this->flatten($node);

        foreach ($allFiles as $item) {
            $items .= $this->renderItem($item);
        }

        $template->setVar(
            [
                'id'    => $node->id(),
                'items' => $items,
            ],
        );

        return $template->render();
    }

    private function renderItem(Node $node, bool $total = false): string
    {
        $data = [
            'numClasses'                      => $node->numberOfClassesAndTraits(),
            'numTestedClasses'                => $node->numberOfTestedClassesAndTraits(),
            'numMethods'                      => $node->numberOfFunctionsAndMethods(),
            'numTestedMethods'                => $node->numberOfTestedFunctionsAndMethods(),
            'linesExecutedPercent'            => $node->percentageOfExecutedLines()->asFloat(),
            'linesExecutedPercentAsString'    => $node->percentageOfExecutedLines()->asString(),
            'numExecutedLines'                => $node->numberOfExecutedLines(),
            'numExecutableLines'              => $node->numberOfExecutableLines(),
            'branchesExecutedPercent'         => $node->percentageOfExecutedBranches()->asFloat(),
            'branchesExecutedPercentAsString' => $node->percentageOfExecutedBranches()->asString(),
            'numExecutedBranches'             => $node->numberOfExecutedBranches(),
            'numExecutableBranches'           => $node->numberOfExecutableBranches(),
            'pathsExecutedPercent'            => $node->percentageOfExecutedPaths()->asFloat(),
            'pathsExecutedPercentAsString'    => $node->percentageOfExecutedPaths()->asString(),
            'numExecutedPaths'                => $node->numberOfExecutedPaths(),
            'numExecutablePaths'              => $node->numberOfExecutablePaths(),
            'testedMethodsPercent'            => $node->percentageOfTestedFunctionsAndMethods()->asFloat(),
            'testedMethodsPercentAsString'    => $node->percentageOfTestedFunctionsAndMethods()->asString(),
            'testedClassesPercent'            => $node->percentageOfTestedClassesAndTraits()->asFloat(),
            'testedClassesPercentAsString'    => $node->percentageOfTestedClassesAndTraits()->asString(),
        ];

        if ($total) {
            // The totals line (at the beginning of the individual files list).
            $data['name'] = 'Total';
        } else {
            if ($this->hasBranchCoverage) {
                // TODO: links need to take into account the urlBase (and basePath).
                $data['name'] = sprintf(
                    '%s <a class="small" href="%s.html">[line]</a> <a class="small" href="%s_branch.html">[branch]</a> <a class="small" href="%s_path.html">[path]</a>',
                    $node->name(),
                    $node->name(),
                    $node->name(),
                    $node->name(),
                );
            } else {
                if ($this->baseUrl) {
                    $data['name'] = sprintf(
                        '<a href="%s.html">%s</a>',
                        $this->baseUrl . str_replace($this->basePath, '', $node->pathAsString()),
                        str_replace($this->basePath, '', $node->pathAsString()),
                    );
                } else {
                    $data['name'] = str_replace($this->basePath, '', $node->pathAsString());
                }
            }
        }

        $templateName = $this->templatePath . ($this->hasBranchCoverage ? 'directory_item_branch.html' : 'directory_item.html');

        $template = new Template($templateName, '{{', '}}');
        $template->setVar([
            'warningColorStart' => '$\textsf{\color{#ffc107}{',
            'colorEnd' => '}}$',
        ]);

        return $this->renderItemTemplate(
            $template,
            $data,
        );
    }

    /**
     * 🟥🟧🟩⬜
     *
     * @param float $percent
     * @return string
     */
    protected function coverageBar(float $percent): string
    {
        $level = $this->colorLevel($percent);

        switch ($level) {
            case 'danger':
                $block = '🟥';
                break;
            case 'warning':
                $block = '🟧';
                break;
            case 'success':
                $block = '🟩';
                break;
            default:
                $block = '⬜';
        }

        $rounded = intval(intval($percent) / 10);
        return str_repeat($block, $rounded) . str_repeat('⬜', 10 - $rounded);
    }
}