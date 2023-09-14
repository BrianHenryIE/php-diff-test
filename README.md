# PHP Diff Test

Run only the tests that cover lines that have been changed.

## Install

```
composer config repositories.brianhenryie/php-diff-test git https://github.com/brianhenryie/php-diff-test
composer require --dev brianhenryie/php-diff-test
```

## Run

Run: `phpunit --filter="$(phpdifftest)"` or `codecept run suitename "$(phpdifftest)"`

## How it works

Runs `git diff`, parses which lines have been changed, parses `*.cov` codecoverage files to match which tests cover those lines.

The script looks in the current working directory, its `tests` subfolder, and each of the `tests` immediate subfolders for `*.cov`.

It also checks `tests` for Codeception `*.suite.y*ml` files, and assumes a file named `unit.cov` corresponds with `unit.suite.yml` to determine should the output be formatted for `codecept run...` syntax rather than PHPUnit `--filter="..."` syntax.

Obviously, it's assumed you're working inside a Git repo and have previously generated code coverage.

## TODO

* I think the diff doesn't track unstaged/committed files which could have code coverage
* Also run tests changed in the diff
* Allow specifying a hash to diff with
* https://github.com/sebastianbergmann/php-code-coverage/issues/571
* Tidy up the code
* Tests!