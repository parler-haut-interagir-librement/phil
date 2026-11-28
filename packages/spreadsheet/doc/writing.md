# Writing

## The workbook

A `Workbook` holds one `Sheet` or several, in order, with distinct names whatever their case. A sheet has:

- a **name** of 1 to 31 characters, without `[ ] : * ? / \` or a control character, without an apostrophe at either
  end, and not `History`, which spreadsheets keep for themselves. `Sheet::nameFrom($title)` makes a valid name from any
  title: the refused characters become spaces, it is cut to 31 characters, `Sheet1` when nothing is left;
- its **rows**, any `iterable` of arrays, read once, in order, while the file is written. A generator keeps the memory
  of one row; the keys of a row are ignored;
- its **columns**, a list of `Column`: the `code` a program reads, the `label` a person reads, and a `width` in
  characters, from 1 to 255. The columns give the heading row and the widths; a sheet without columns has neither.

`Workbook::single($name, $rows, $columns)` is the workbook of one sheet.

## The cells and their types

A `Cell` has a `CellType` and a value. The type is stated by the code, never guessed from the text:

| Type | Value | Made by |
|---|---|---|
| `Text` | a string | `Cell::text()`, or any PHP string |
| `Number` | an integer, or a finite float | `Cell::number()`, or any PHP integer or float |
| `Boolean` | a boolean | `Cell::boolean()`, or any PHP boolean |
| `Date` | a `DateTimeImmutable` at midnight | `Cell::date()` |
| `DateTime` | a `DateTimeImmutable` | `Cell::dateTime()`, or any `DateTimeInterface` |
| `Empty` | null | `Cell::empty()`, null, or an empty string |

A row may hold cells, PHP values, or both: `Cell::from()` turns a PHP value into a cell by its PHP type alone, so the
string `'00123'` stays a text with its zeros, and `'42'` stays a text too. Any other value — an array, an object other
than a date — is refused with an `InvalidSpreadsheet`, as are an infinite number and `NAN`.

A date is written at its wall-clock time, its time zone dropped: spreadsheets have no time zones. Convert a date to the
zone the reader of the file expects before writing it.

## The options

`WriteOptions` sets, for every sheet of the workbook:

- `headings`: `Headings::Labels` (the default) writes the label of each column, or its code when it has none;
  `Headings::Codes` writes the codes; `Headings::None` writes no heading row;
- `range`: a `RowRange` of the rows of data written, counted from 1 without the heading row, both ends included. The
  rows after the last are never read from their iterable, so a range over a generator stops the query behind it;
- `guardFormulas`: true by default. See [Security](security.md).

```php
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\RowRange;
use Phil\Spreadsheet\WriteOptions;

$writer->write($workbook, $stream, new WriteOptions(Headings::Codes, new RowRange(1, 500)));
```

## The writers

Each writer implements `Contract\SpreadsheetWriter`: `format()`, and `write(Workbook, $stream, WriteOptions)`, which
writes to an open stream and leaves it open. `Spreadsheets::writer()` gives the writer of a `Format`, of an extension,
of a file name or of a media type; `Spreadsheets::writeFile()` writes to a path.

### CSV

`CsvWriter` writes RFC 4180, straight to the stream, row by row. Its `CsvDialect` sets the delimiter (`,`), the
enclosure (`"`), the line ending (`\r\n` or `\n`) and the UTF-8 byte order mark spreadsheets need to read accents
(written by default). `CsvDialect::semicolon()` is the dialect of spreadsheets set to a language that writes decimals
with a comma.

A CSV file has no types and one sheet: a workbook of more than one sheet is refused. A number is written as `12.5`, a
boolean as `TRUE` or `FALSE`, a date as `2026-11-21`, a date and time as `2026-11-21 14:30:00`, with its fraction of a
second when it has one. A failure half way leaves the rows already written in the stream.

### XLSX

`XlsxWriter` writes an Office Open XML workbook: each sheet's rows go to a temporary file in chunks of 64 KiB, then the
parts are zipped with ext-zip and copied to the stream. The stream receives nothing when writing fails, and the
temporary files are removed either way; they go to the directory given to the writer, or the system's.

- A text is an inline string, which a spreadsheet never runs as a formula; the characters XML cannot hold are written
  as the format's `_xHHHH_` escapes.
- A number and a boolean keep their kind.
- A date is a serial number formatted `yyyy-mm-dd`, a date and time one formatted `yyyy-mm-dd hh:mm:ss`. The format
  writes the days from 1900-03-01 to 9999-12-31: a date outside them is refused.
- The heading row is bold and frozen; the columns with a width have it.
- A sheet holds at most 1,048,576 rows, the heading row included, and 16,384 columns; a text at most 32,767 characters.
  Past them, the workbook is refused with a `WriteFailed`. The writer's second argument lowers the most rows of a
  sheet, to bound an export.

Every entry of the archive carries the same date, so the same workbook gives the same bytes.

### ODS

`OdsWriter` writes an OpenDocument spreadsheet, version 1.3, the same way: the content goes to a temporary file in
chunks, then the archive is zipped — its `mimetype` first and stored, as the format requires — and copied to the
stream.

- A text is a string cell, one paragraph per line, with its tabs and the spaces XML would collapse kept; the control
  characters XML cannot hold become U+FFFD.
- A number is a float cell, a boolean a boolean cell, a date a date cell formatted `yyyy-mm-dd` or
  `yyyy-mm-dd hh:mm:ss`, at any year.
- The heading row is bold; the columns with a width have it, at 0.2 cm a character.
- A sheet holds at most 1,048,576 rows, the heading row included — fewer when the writer's second argument says so —
  and 16,384 columns.

## Sending a file from a controller

A writer writes to any stream: `php://output` in a streamed response, or a temporary stream handed to a file
response.

```php
use Symfony\Component\HttpFoundation\StreamedResponse;

return new StreamedResponse(static function () use ($spreadsheets, $workbook): void {
    $spreadsheets->writer(Format::Xlsx)->write($workbook, fopen('php://output', 'w'));
}, 200, [
    'Content-Type' => Format::Xlsx->mediaType(),
    'Content-Disposition' => 'attachment; filename="answers.xlsx"',
]);
```
