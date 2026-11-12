# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. It writes every kind of cell in
both formats, the guard against formulas, the byte order mark and the separators, reads the workbooks back with
ZipArchive and DOM, checks the limits, the names of worksheets, the same bytes for the same sheet, and that no
temporary file is left behind. In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/cell.php` writes text nobody wrote as a cell: in XLSX it always makes well-formed XML, and in CSV the guard
never lets it start as a formula. From the monorepo root:

```sh
composer fuzz -- cell --budget=60
```

## The benchmarks

`tests/Benchmark/ExportBench.php` writes ten thousand rows of five cells as CSV and as XLSX, from the monorepo root:

```sh
composer bench -- --filter=ExportBench
```
