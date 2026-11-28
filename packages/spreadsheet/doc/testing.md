# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network, in a temporary folder of their own that each test removes.

- The files each writer writes are read back by the reader of the same format, cell by cell, in
  `tests/Unit/SpreadsheetsTest`.
- The XML parts the XLSX and ODS writers write are parsed with `Dom\XMLDocument` and checked for their structure: the
  entries of the archive and their order, the content types, the relationships, the sheets, the styles, the manifest,
  and the `mimetype` stored first.
- The readers are tested on archives written by hand in `tests/Support/Workbooks`: shared texts, styles and date
  formats, the 1904 date system, a workbook at the root of its archive, repeated ODS rows and cells, and the files
  they refuse — entries outside the root, a document type with an external entity, a part over its size, an encrypted
  part, a truncated part.

In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz targets

The three readers have a fuzz target each, run by `composer fuzz`, with small limits:

| Target | What it reads | Seeds |
|---|---|---|
| `spreadsheet-csv` | `CsvReader` on any bytes | `tests/Fuzz/corpus/spreadsheet-csv` |
| `spreadsheet-xlsx-sheet` | the XLSX worksheet parser on any XML, with two shared texts and two date styles | `tests/Fuzz/corpus/spreadsheet-xlsx-sheet` |
| `spreadsheet-ods-content` | the ODS content parser on any XML | `tests/Fuzz/corpus/spreadsheet-ods-content` |

Each read returns rows or throws a `ReadRefused`: nothing else escapes, no warning is raised, and every row given lies
within the limits, its last cell not empty.

```sh
composer fuzz -- spreadsheet-xlsx-sheet
```

## The benchmarks

`tests/Benchmark/SpreadsheetBench` writes 100,000 rows of ten cells — texts, a product code, an integer, a float, a
boolean, a date and empty cells — as CSV, XLSX and ODS, and reads them back:

```sh
composer bench -- --filter=SpreadsheetBench
```

The peak memory is that of a row and a chunk, the same for 1,000 rows as for 100,000. On a laptop of 2026, with PHP
8.5 and without OPcache, the times are about 1.3 s to write CSV, 3.6 s XLSX and 1.9 s ODS, and 0.9 s to read CSV,
4.7 s XLSX and 4.9 s ODS, at a peak of 5.3 MiB.
