# Reference

Every public type of the package, with its role. The types marked internal are not part of the public API.

## The model

| Type | Role |
|---|---|
| `Cell` | One cell: `type` and `value`. `text()`, `number()` (finite), `boolean()`, `date()` (midnight), `dateTime()`, `empty()`; `from(mixed)` types a PHP value by its PHP type; `isEmpty()`. An empty text is an empty cell. |
| `CellType` | `Empty`, `Text`, `Number`, `Boolean`, `Date`, `DateTime`. |
| `Column` | A column to write: `code`, `label` (the code when null), `width` in characters (1 to 255); `heading(Headings)`. |
| `Sheet` | A sheet to write: `name` (1 to 31 characters a spreadsheet allows), `rows` (any iterable of arrays, read once), `columns`; `nameFrom(string)` makes a valid name; `NAME_LENGTH` is 31. |
| `Workbook` | The sheets of a file to write, at least one, with distinct names: `sheets`; `single($name, $rows, $columns)`. |
| `Headings` | `Labels`, `Codes`, `None`: the heading row written. |
| `RowRange` | The rows of data written: `first` (from 1), `last` (null for every row). |
| `WriteOptions` | `headings` (`Labels`), `range` (every row), `guardFormulas` (true). |
| `Row` | A row read: `number` (from 1), `cells` without the empty ones at the end; `cell(int)`, `values()`. |
| `ReadLimits` | `maxFileBytes`, `maxEntries`, `maxPartBytes`, `maxSharedStringsBytes`, `maxRows`, `maxColumns`, `maxCells`, each 1 or more. |
| `Format` | `Csv`, `Xlsx`, `Ods`: `extension()`, `mediaType()`; `detect(string)` from an extension, a file name or a media type. |
| `Spreadsheets` | `writer(Format\|string)`, `reader(Format\|string)` and `writeFile(Workbook, string $path, ?Format, WriteOptions)`, with the limits, the CSV dialect and the temporary directory it was given. |

## The contracts

| Type | Role |
|---|---|
| `Contract\SpreadsheetWriter` | `format()`; `write(Workbook, resource $stream, WriteOptions)`. |
| `Contract\SpreadsheetReader` | `format()`; `sheetNames(string $path)`; `rows(string $path, ?string $sheet)`, a generator of `Row`. |

## CSV

| Type | Role |
|---|---|
| `Csv\CsvDialect` | `delimiter` (`,`), `enclosure` (`"`), `lineEnding` (`\r\n` or `\n`), `byteOrderMark` (true); `semicolon()`. |
| `Csv\CsvWriter` | Writes one sheet as RFC 4180, straight to the stream; `text(Cell, bool $guardFormulas)`, a cell's text in a CSV file. |
| `Csv\CsvReader` | Reads UTF-8 records as texts, within the limits; the byte order mark skipped. |

## XLSX

| Type | Role |
|---|---|
| `Xlsx\XlsxWriter` | Writes an Office Open XML workbook through temporary files: `temporaryDirectory`, `rowLimit` (at most `ROW_LIMIT`, 1,048,576); `COLUMN_LIMIT` 16,384. |
| `Xlsx\XlsxReader` | Reads the rows of a workbook, transitional or strict, node by node from the archive. |
| `Xlsx\XlsxSheetParser` | Internal. The rows of a worksheet part. |
| `Xlsx\ExcelDate` | Internal. The serial numbers of dates, in the 1900 and 1904 systems. |
| `Xlsx\NumberFormats` | Internal. Which number formats show a date, or a date and time. |
| `Xlsx\XlsxText` | Internal. The texts of a workbook escaped for XML and `_xHHHH_`, and their length. |
| `Xlsx\CellReference` | Internal. The letters of a column. |

## ODS

| Type | Role |
|---|---|
| `Ods\OdsWriter` | Writes an OpenDocument spreadsheet, version 1.3, through temporary files: `temporaryDirectory`, `rowLimit` (at most `ROW_LIMIT`, 1,048,576); `COLUMN_LIMIT` 16,384, `MEDIA_TYPE`. |
| `Ods\OdsReader` | Reads the rows of a spreadsheet or a template, node by node from the archive. |
| `Ods\OdsContentParser` | Internal. The tables of `content.xml`, their names, and the table read. |
| `Ods\OdsTableParser` | Internal. The rows of one table, with their repetitions. |
| `Ods\OdsText` | Internal. A text as paragraphs, tabs and kept spaces. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\SpreadsheetException` | The interface of every exception of the library. |
| `Exception\InvalidSpreadsheet` | An argument refused: a sheet name, a cell value, a limit, a dialect, a format, a stream. |
| `Exception\WriteFailed` | A file that cannot be written: a value or a size the format cannot hold, a stream or a temporary file that refuses the bytes. |
| `Exception\ReadRefused` | A file refused: unreadable, over a limit, unsafe or malformed. |

## Internal

| Type | Role |
|---|---|
| `Internal\Archive` | A ZIP archive read within limits: entries inside the root, parts through a byte budget. |
| `Internal\SafeXmlReader` | An XML part read node by node, without a document type, the network or warnings. |
| `Internal\ByteBudget`, `Internal\BudgetFilter` | The bytes a part may still give, and the stream filter that counts them. |
| `Internal\RowBuilder` | The rows of a read: gaps filled, ends trimmed, limits held. |
| `Internal\SheetRows` | The heading row and the rows of data a writer writes. |
| `Internal\BufferedFile`, `Internal\Output` | The temporary files, the archives and the streams of the writers. |
| `Internal\FormulaGuard` | The texts a spreadsheet would run as a formula. |
| `Internal\Values` | The texts of numbers and dates, and the parsing of numbers and ISO dates. |
