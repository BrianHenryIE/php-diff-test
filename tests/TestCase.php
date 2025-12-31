<?php

/**
 * Base test case that closes Mockery after each test.
 */

namespace BrianHenryIE\PhpDiffTest;

use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Close Mockery so `::once()` etc. expectations are verified.
     */
    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
