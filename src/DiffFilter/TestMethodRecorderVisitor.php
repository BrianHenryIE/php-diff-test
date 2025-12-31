<?php

/**
 * Get a list of test methods in a PHP file using PhpParser.
 *
 * Finds `function test*()` rather than annotated `@test` methods.
 *
 * While a code coverage report is used to find most test methods, this is used to find tests that have only been
 * written since the beginning of the diff.
 */

namespace BrianHenryIE\PhpDiffTest\DiffFilter;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

class TestMethodRecorderVisitor extends NodeVisitorAbstract
{
    /** @var string $namespace */
    protected $namespace;
    /** @var string $class */
    protected $class;

    /**
     * The fqdn test methods, with the range indicating start and end lines.
     *
     * @var array<string, array{0:int, 1:int}>
     */
    protected $methods = [];

    public function enterNode(Node $node)
    {
        // Record the namespace as we pass it.
        if ($node instanceof Namespace_ && !is_null($node->name)) {
            $this->namespace = $node->name->toString();
        }

        // Record the class name as we pass it.
        if ($node instanceof ClassLike && !is_null($node->name)) {
            /**
             * @see \PhpParser\Node\Stmt\Class_
             */
            $this->class = $node->name->toString();
        }

        // Record test method names.
        if ($node instanceof ClassMethod && $this->isTestMethod($node)) {
            $this->addMethod($node);
        }

        return $node;
    }

    /**
     * Check if the method is a test method.
     *
     * This checks if the method name starts with 'test'.
     */
    protected function isTestMethod(ClassMethod $node): bool
    {
        return str_starts_with($node->name->toString(), 'test');
    }

    protected function addMethod(ClassMethod $node): void
    {
        $fqdnTestMethod = $this->namespace . '\\' . $this->class . '::' . $node->name->toString();
        $range          = [$node->getStartLine(), $node->getEndLine()];

        $this->methods[$fqdnTestMethod] = $range;
    }

    /**
     * @return array<string, array{0:int, 1:int}>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
