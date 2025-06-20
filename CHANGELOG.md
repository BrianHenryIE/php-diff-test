# Change Log

## 0.9.0 June 2025

* Add: `--covered-files` allow list to `markdown-report`
* Fix: check file exists before trying to count the number of lines in it
* Fix: remove data provider indexes
* Improve: hyperlinking in `markdown-report` – `--base-url=https://github.com/<company/project>/blob/<sha>/%s`

## 0.8.1 January 2025

* Rename the phar to `php-diff-test` (deprecate `difftest`) because Phive is going to publish it as that anyway
* Add some null checks

## 0.8.0 November 2024

* Add markdown report – `difftest markdown-report --input-file=/path/to/php.cov` – for use in GitHub Actions 
* Fix: Handle exception when code coverage file cannot be parsed

## 0.7.1 November 2024

* Fix: attach the GPG signature to the release!

## 0.7.0 November 2024

* Add GPG signing for Phive
* Fix: non-php files were appearing in the report (pt2)

## 0.6.1 November 2024

* Fix: non-php files were appearing in the report
* Fix: mistakenly using array in array

## 0.6.0 November 2024

* Determine new/modified/uncommitted tests to run
* Use `HEAD~0` as default `--diff-to` value

## 0.5.0 November 2024

* Merge both scripts into one application with subcommands

## 0.4.0 November 2024

* Add `difffilter` `--granularity=line|file` parameter

## 0.3.0 November 2024

* Add `diffcoverage` to filter existing code coverage report file to just files changed in the diff, a step for printing a focused HTML report
* Rename `phpdifftest` to `difffilter`
* General refactoring + using symfony/console now

## 0.2.0 November 2023

* Publish on Packagist

## 0.1.0 February 2023

* Basics working