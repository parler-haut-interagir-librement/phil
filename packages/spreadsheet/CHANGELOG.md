# Changelog

All notable changes to `phil/spreadsheet` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- The model of a workbook: `Cell` and `CellType` — text, number, boolean, date, date and time, empty — stated by the
  code and never guessed, `Cell::from()` typing a PHP value by its PHP type alone; `Column` with its code, label and
  width; `Sheet` with a name a spreadsheet allows and `Sheet::nameFrom()`; `Workbook` of sheets with distinct names.
- `WriteOptions`: the heading row by labels, by codes or none (`Headings`), the range of rows written (`RowRange`),
  whose rows after the last are never read, and the formula guard, on by default.
- `CsvWriter` and `CsvDialect`: RFC 4180 written row by row to the stream, the UTF-8 byte order mark, the semicolon
  dialect, ISO dates, `TRUE` and `FALSE`, and an apostrophe in front of a text starting with `=`, `+`, `-`, `@`, a tab
  or a carriage return.
- `XlsxWriter`: an Office Open XML workbook of one sheet or several, written in chunks to temporary files and zipped
  with ext-zip; inline strings with `_xHHHH_` escapes, typed numbers and booleans, dates as serial numbers from
  1900-03-01 formatted `yyyy-mm-dd` or `yyyy-mm-dd hh:mm:ss`, the quote prefix on a text that would run as a formula,
  a bold and frozen heading row, column widths, the limits of the format — the most rows lowered on demand —, and the
  same bytes for the same workbook.
- `OdsWriter`: an OpenDocument spreadsheet, version 1.3, written the same way, its `mimetype` first and stored; string
  cells as paragraphs with their tabs and spaces kept, float, boolean and date cells, a bold heading row, column widths.
- `CsvReader`: UTF-8 records read as texts, the byte order mark skipped, a record that is not UTF-8 refused.
- `XlsxReader`: the rows of a workbook of the transitional or the strict schema, read node by node from the archive;
  shared and inline texts without their phonetic runs, numbers, booleans, errors, the values formulas saved, and the
  numbers under a date format read as dates in the 1900 or 1904 system, to the millisecond.
- `OdsReader`: the rows of a spreadsheet or a template; string, float, percentage, currency, date, boolean and time
  cells, paragraphs joined by line breaks, annotations and drawings left out, and repeated rows and cells read without
  filling memory.
- `ReadLimits` and the defences of the readers: the size of the file, the entries of an archive, the bytes of each
  part counted as they are uncompressed, the rows, columns, cells and the memory of the shared texts bounded; entries
  outside the archive's root, targets above it, document type declarations and encrypted parts refused; no network,
  no warning raised, nothing extracted to the disk.
- `Spreadsheets` and `Format`: the writer or the reader of a format, an extension, a file name or a media type, with
  the limits, the CSV dialect and the temporary directory given; `writeFile()`.
- The `SpreadsheetWriter` and `SpreadsheetReader` contracts, and `InvalidSpreadsheet`, `WriteFailed` and
  `ReadRefused` under `SpreadsheetException`.
- The fuzz targets `spreadsheet-csv`, `spreadsheet-xlsx-sheet` and `spreadsheet-ods-content`, the benchmark
  `SpreadsheetBench` of 100,000 rows, and the documentation in `doc/`: installation, writing, reading, security,
  extension points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
