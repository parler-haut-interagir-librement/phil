# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Write another format | Implement `Contract\SpreadsheetWriter`: `format()` and `write(Workbook, $stream, WriteOptions)` |
| Read another format | Implement `Contract\SpreadsheetReader`: `format()`, `sheetNames(string $path)` and `rows(string $path, ?string $sheet)` |
| Choose a writer or a reader in a service | Type the dependency by the contract, and let `Spreadsheets` or the container give it |
| Read a CSV file of another shape | Pass a `CsvDialect` to `Spreadsheets`, `CsvWriter` or `CsvReader` |
| Bound the reads of an application | Pass `ReadLimits` to `Spreadsheets` or to a reader |
| Put the temporary files of XLSX and ODS elsewhere | Pass a directory to `Spreadsheets`, `XlsxWriter` or `OdsWriter` |

An implementation of the contracts keeps no state between two calls, as the package's own do, so that one instance
serves a long-running worker.

A writer reads each sheet's rows once, in order, and applies the options: the heading row of the columns, the range of
rows — reading no row after its last — and the formula guard. A reader gives the rows that hold a cell, with their
number, without the empty cells at their end, and refuses with a `ReadRefused` a file over its limits.

`Format` lists the formats `Spreadsheets` knows; a writer or a reader of another format is used directly, or through
the application's own factory.

The classes of the `Internal` namespace, and those marked `@internal` in the `Xlsx` and `Ods` namespaces, are not part
of the API: they change without notice.
