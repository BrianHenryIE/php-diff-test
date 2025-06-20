<?php

/**
 * Used as an option for the list of tests to return in the filter – only those that match the changed lines
 * specifically, or all tests that cover a file that has changed.
 */

namespace BrianHenryIE\PhpDiffTest;

enum Granularity: string
{
    case FILE = 'file';
    case LINE = 'line';
}
