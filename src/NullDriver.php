<?php

/**
 * A code coverage driver is responsible for collecting code execution data during test runs.
 * Typically, drivers like Xdebug, PCOV, or PHPDBG instrument PHP code to track which lines
 * are executed during testing.
 *
 * This NullDriver is a no-op implementation that doesn't actually collect any coverage data.
 * It allows the code coverage framework to be initialized without requiring coverage extensions
 * like Xdebug (with XDEBUG_MODE=coverage) to be enabled. This is useful when you only need
 * to work with pre-existing coverage data files rather than collecting new coverage.
 *
 * Note: This won't cause issues when exported coverage files are used by other tools since
 * this driver is only used internally and doesn't affect the coverage data file format.
 */

namespace BrianHenryIE\PhpDiffTest;

use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;

class NullDriver extends Driver
{
    public function nameAndVersion(): string
    {
        return 'nulldriver';
    }

    public function start(): void
    {
    }

    public function stop(): RawCodeCoverageData
    {
        throw new \BadMethodCallException('Not implemented');
//        return RawCodeCoverageData::fromUncoveredFile()
//        return RawCodeCoverageData::fromXdebugWithoutPathCoverage();
//        new RawCodeCoverageData()
    }
}
