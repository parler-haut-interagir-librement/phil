# phil/spreadsheet

**Read and write CSV, XLSX and ODS in constant memory, with explicit cell types, formula injection guarded and
untrusted files read within limits.**

phil/spreadsheet is a plain PHP library. It writes a workbook — one sheet or several, each with its columns and the
rows read once from any iterable — to a stream, as CSV, as Office Open XML (XLSX) or as OpenDocument (ODS), and reads
the rows of such a file back, one row at a time. Both directions keep the memory of one row and of one chunk whatever
the number of rows: an export of a million answers runs in a background job without growing.

Every cell has a type stated by the code, never guessed from its text: a text, a number, a boolean, a date, a date
and time, or nothing. A postal code or a product code written as text keeps its leading zeros, and reads back as
text. A text a spreadsheet would run as a formula — `=`, `+`, `-`, `@`, a tab or a carriage return at its start — is
kept as text by default (OWASP, CSV injection).

The readers take files nobody checked: the size of the file, the entries of an archive, the bytes of each part as
they are uncompressed, the rows, columns and cells are bounded; an entry outside the archive's root, a document type
declaration and an external entity are refused; nothing is ever extracted to the disk.

The library writes data tables: no formulas, charts or rich formatting, a bold and frozen heading row and column
widths at most.

## Status

| Part | State |
|---|---|
| `Cell`, `CellType`, `Column`, `Sheet`, `Workbook`, `WriteOptions`, `Headings`, `RowRange`: the model of a workbook | Implemented and tested |
| `CsvWriter`, `CsvReader`, `CsvDialect`: RFC 4180, byte order mark, formula guard, dialects | Implemented and tested |
| `XlsxWriter`, `XlsxReader`: inline strings, typed cells, dates, shared texts, 1900 and 1904 date systems, strict schema | Implemented and tested |
| `OdsWriter`, `OdsReader`: typed cells, paragraphs and spaces, repeated rows and cells | Implemented and tested |
| `ReadLimits` and the defences of the readers: ZIP bomb, entries outside the root, XXE, sizes counted as read | Implemented and tested |
| `Spreadsheets` and `Format`: a writer or a reader by format, extension or media type | Implemented and tested |
| The fuzz targets of the three readers and the benchmarks of 100,000 rows | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and a first export and import. |
| [Writing](doc/writing.md) | The workbook, the cells and their types, the options, each format's output and limits. |
| [Reading](doc/reading.md) | The rows read, the types of each format, the sheets, and the limits. |
| [Security](doc/security.md) | The formula guard, and how the readers handle files nobody checked. |
| [Extension points](doc/extension-points.md) | The writer and reader contracts, and when the API is stable. |
| [Testing](doc/testing.md) | The tests, the fuzz targets and the benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/spreadsheet
```

```php
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Workbook;

$rows = (function () use ($answers): iterable {
    foreach ($answers as $answer) {
        yield [$answer->postalCode, $answer->score, $answer->submittedAt];
    }
})();

$spreadsheets = new Spreadsheets();
$spreadsheets->writeFile(
    Workbook::single('Answers', $rows, [new Column('postal_code', 'Postal code', 12), new Column('score', 'Score'), new Column('at', 'Submitted')]),
    '/var/exports/answers.xlsx',
);

foreach ($spreadsheets->reader('answers.xlsx')->rows('/var/exports/answers.xlsx') as $row) {
    [$postalCode, $score, $submittedAt] = $row->values();
}
```

## Requirements

PHP 8.5 or later, with `ext-libxml`, `ext-mbstring`, `ext-xmlreader` and `ext-zip`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
