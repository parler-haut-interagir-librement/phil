# Reading

## The readers

Each reader implements `Contract\SpreadsheetReader`:

- `sheetNames(string $path)`: the names of the file's sheets, in order;
- `rows(string $path, ?string $sheet = null)`: a generator of the `Row`s of a sheet, the first when no name is given.

`Spreadsheets::reader()` gives the reader of a `Format`, of an extension, of a file name or of a media type, set with
the factory's `ReadLimits` and CSV dialect. The extension or the media type a browser sent only chooses the reader:
the reader checks the bytes, and a file that is not what it claims is refused.

The file is opened when the first row is taken, and read as the rows are taken: memory stays that of one row, and a
limit reached half way throws a `ReadRefused` then. Stop taking rows and the file is closed.

## The rows

A `Row` has its `number` in the sheet, from 1, as the spreadsheet shows it, and its `cells`, a list of `Cell`. The
empty cells between two cells are there; the empty cells at the end of a row are not; a row with no cell left is never
given, so the numbers of the rows given may have gaps. `cell($index)` gives the cell at a column, from 0, an empty one
past the end; `values()` gives the values of the cells, null for an empty one.

## The types read

### CSV

`CsvReader` reads UTF-8 with its `CsvDialect` — the delimiter and the enclosure; the byte order mark is skipped
whatever the dialect says. A CSV file has no types: **every cell read is a text**, and an empty field an empty cell.
The code that reads it states the types, as it does when writing. A row's number counts the records, blank lines
included. The file is one sheet, named after the file without its extension. A record that is not UTF-8 is refused.

### XLSX

`XlsxReader` reads workbooks of the transitional and of the strict schema:

- a shared text, an inline text, the text a formula saved and an error such as `#DIV/0!` are texts, with the format's
  `_xHHHH_` escapes decoded and the phonetic runs left out;
- a number is an integer when it is written as one and fits, a float otherwise;
- a number under a date format is a date, or a date and time when the format shows a time, in UTC, to the millisecond;
  the 1900 date system — with its 29 February 1900 that never was — and the 1904 one are both read. A date cell of
  the strict schema, written in ISO 8601, is read the same way;
- a boolean is a boolean;
- a formula is not run: the cell gives the value the spreadsheet saved with it.

### ODS

`OdsReader` reads spreadsheets and their templates:

- a string cell is a text, its paragraphs joined by line breaks, with its spaces, tabs and line breaks; annotations and
  drawings in a cell are left out;
- a float, percentage or currency cell is a number;
- a date cell is a date, or a date and time when it has a time;
- a boolean cell is a boolean;
- a time of day is the text it shows;
- a row or a cell a spreadsheet repeats gives that many rows or cells; repeated empty ones, which a spreadsheet
  writes to the end of the sheet, only move the count.

## The limits

`ReadLimits` bounds every read; a file past any limit is refused, never read in part:

| Limit | Default | What it bounds |
|---|---|---|
| `maxFileBytes` | 256 MiB | the size of the file, CSV file or archive |
| `maxEntries` | 1,000 | the entries of an archive |
| `maxPartBytes` | 1 GiB | the bytes of one part of an archive once uncompressed, counted as they are read |
| `maxSharedStringsBytes` | 128 MiB | the memory the shared texts of an XLSX workbook take, each counted with 32 bytes more |
| `maxRows` | 1,048,576 | the highest number of a row with a cell |
| `maxColumns` | 16,384 | the columns of a row |
| `maxCells` | 50,000,000 | the cells given by one read, the empty ones inside the rows included |

Lower them for the files people upload: an import of a few thousand rows needs a few megabytes, not 256.

```php
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Spreadsheets;

$spreadsheets = new Spreadsheets(new ReadLimits(maxFileBytes: 8 * 1024 * 1024, maxRows: 20_000, maxColumns: 100));
```

The shared texts of an XLSX workbook are the one part a reader keeps whole, as the rows refer to them by index:
`maxSharedStringsBytes` bounds that memory.
