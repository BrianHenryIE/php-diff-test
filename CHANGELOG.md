# Change Log

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

## 0.4.0 November 2023

* Add `difffilter` `--granularity=line|file` parameter

## 0.3.0 November 2023

* Add `diffcoverage` to filter existing code coverage report file to just files changed in the diff, a step for printing a focused HTML report
* Rename `phpdifftest` to `difffilter`
* General refactoring + using symfony/console now

## 0.2.0 November 2023

* Publish on Packagist