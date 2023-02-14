# PHP Diff Test

Run only the tests that cover lines that have been changed.

Work in progress.

Runs `git diff`, parses which lines have been changed, parses `*.cov` codecoverage files to match which tests cover those lines, runs only those tests.

```
composer config repositories.brianhenryie/php-diff-test git https://github.com/brianhenryie/php-diff-test
composer require --dev brianhenryie/php-diff-test
```

Run: `vendor/bin/phpdifftest`

The script looks in the current working directory, its `tests` subfolder, and each of the `tests` immediate subfolders for `*.cov`.

It also checks `tests` for Codeception `*.suite.y*ml` files, and assumes a file named `unit.cov` corresponds with `unit.suite.yml` and runs `codecept run unit ...` for tests found in `unit.cov`.

TODO: Otherwise it runs the tests with regular PHPUnit.

Test classes must be autoloadable by Composer.

Obviously, it's assumed you're working inside a Git repo and have previously generated code coverage.

https://github.com/sebastianbergmann/php-code-coverage/issues/571
