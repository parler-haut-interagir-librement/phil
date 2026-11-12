# Changelog

All notable changes to `phil/data-table-export` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `Sheet`, a title, headings and rows read once from any iterable; its cells hold nothing, booleans, numbers, texts,
  dates, backed enums or `Stringable`, anything else refused with `Exception\ExportRefused`.
- `Format`, CSV and XLSX, read from a name, with the media type and a safe file name.
- `Contract\ExportWriter`, and `ExportWriters`, one writer per format, CSV and XLSX by default.
- `Csv\CsvWriter`, RFC 4180 written row by row: a chosen separator, the UTF-8 byte order mark, and `Csv\FormulaGuard`,
  which keeps a text a spreadsheet would run as a formula as text and leaves signed numbers alone.
- `Xlsx\XlsxWriter`, a workbook of one worksheet written with ext-zip and XML in chunks through temporary files that are
  always removed: inline strings that never run, typed numbers, booleans and dates, bold frozen headings, a safe
  worksheet name, the limits of a spreadsheet and a lower row limit, the same bytes for the same sheet.
- The fuzz target of a cell's text and the benchmarks of both formats.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
