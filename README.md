# PHP Coverage Filter

* Run only the tests that cover lines that have been changed.
* View the report for only files that have been changed. 

## Install

```
composer require --dev brianhenryie/php-diff-test
```

Requires `XDEBUG_MODE=coverage`.

## Run

### `difffilter` 

Prints a filter to use with PHPUnit or Codeception, so you only run tests relevant to changes in the branch you're working on.

* Run: `phpunit --filter="$(difffilter)"` or `codecept run suitename "$(difffilter)"`.
* Try just `difffilter` to see the filter that will be applied, which is effectively `difffilter --input-files <glob *.cov> --diff-from main --diff-to HEAD^ --granularity=line`
* Try `difffilter --diff-from HEAD~3` to print a shallower filter
* Try `difffilter --granularity=file` to print a filter which includes all tests that cover any line in changed files (this makes the HTML report make more sense)

### `diffcoverage`

Outputs a new `.cov` file containing only the files whose lines have been changed in the diff. Intended to then print a HTML coverage report

* Run: `diffcoverage --input-files "php-coverage1.cov,php-coverage2.cov" --diff-from main --diff-to HEAD^ --output-file diff-coverage/diff-from-to.cov`
* Then to generate the new HTML report: `phpcov merge --html ./diff-coverage/report ./diff-coverage`. NB `phpcov` will merge all `.cov` files in the directory and subdirectories so you should set `diffcoverage`'s new `.cov` `--output-file` to be in its own directory.

## How it works

Runs `git diff`, parses which lines have been changed, parses `*.cov` codecoverage files to match which tests cover those lines.

The script looks in the current working directory, its `tests` subfolder, and each of the `tests` immediate subfolders for `*.cov`.

It also checks `tests` for Codeception `*.suite.y*ml` files, and assumes a file named `unit.cov` corresponds with `unit.suite.yml` to determine should the output be formatted for `codecept run...` syntax rather than PHPUnit `--filter="..."` syntax.

Obviously, it's assumed you're working inside a Git repo and have previously generated code coverage (in PHP `.cov` format).

> ⚠️ This has been tested to just over 500 test cases. It will inevitably have its limits, when you should probably use [groups](https://docs.phpunit.de/en/10.5/annotations.html#group) to first generate the coverage to be filtered.

## TODO

* ~~I think the diff doesn't track unstaged/uncommitted files which could have code coverage~~
* Also run tests changed in the diff / run all tests changed since the code coverage was generated (i.e. Tests written after the code coverage report are not included in the filter)
* Figure how best merge/increment coverage reports – i.e. once `diffcoverage` has been run on the full coverage report, add to it when new tests are written
* ~~Allow specifying a hash to diff with – i.e. make pull requests run faster~~
* https://github.com/sebastianbergmann/php-code-coverage/issues/571 – code coverage annotations make this tool less thorough 
* ~~Tidy up the code – I'm not sure is the diff report's lines the current lines or the before lines... use both for best effect~~
* Tests!
