# XLSX

`XlsxWriter` writes an Office Open XML workbook of one worksheet, readable by LibreOffice, Excel, Numbers and Google
Sheets, with ext-zip and XML only.

```php
new XlsxWriter(
    directory: null,                     // where the temporary files go, sys_get_temp_dir() when null
    rowLimit: XlsxWriter::ROW_LIMIT,     // the most rows, headings included: 1 to 1,048,576
);
```

## The workbook

- The headings are the first row, bold, frozen above the rows that scroll.
- A text is an inline string: never a formula, so nothing a person typed runs when the file opens. It is kept to valid
  UTF-8 and to the characters XML 1.0 allows, and cut at the 32,767 characters a cell holds.
- A number and a boolean keep their kind; `INF` and `NAN`, which a workbook cannot hold, are written as text.
- A date is a serial number of days, styled `yyyy-mm-dd` at midnight and `yyyy-mm-dd hh:mm:ss` otherwise. A date before
  1 March 1900, which spreadsheets count wrong, is written as text.
- The title names the worksheet: the characters `[ ] : * ? / \` and control characters become spaces, an apostrophe
  at either end is dropped, it is cut at 31 characters, and it is `Sheet1` when nothing is left.
- Every entry of the archive carries the same date, so the same sheet always gives the same bytes.

## Memory and temporary files

The rows are written to a temporary file in chunks of 64 KiB, then the parts are zipped into a second temporary file,
which is copied to the stream. Memory stays that of a chunk, whatever the number of rows; the disk holds the worksheet
twice while the file is written. Both temporary files are removed when the writing ends, refused or not.

## Limits

A sheet of more rows than `rowLimit` — 1,048,576 at most, the limit of a spreadsheet — or of a row of more than 16,384
cells stops with `ExportRefused`, and nothing is written to the stream: the workbook is copied only once complete.
