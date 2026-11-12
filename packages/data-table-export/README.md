# phil/data-table-export

**Rows written to CSV and XLSX files as they are read, in bounded memory.**

phil/data-table-export is a plain PHP library. It writes a sheet — a title, the headings and rows read once from any
iterable — to a stream, as CSV or as an XLSX workbook. It knows nothing of where the rows come from: a data table, a
report or a console command hands it a generator and a stream.

CSV follows RFC 4180, starts with a UTF-8 byte order mark, and keeps every text that a spreadsheet would run as a
formula as plain text. XLSX is written with ext-zip and XML, its texts as inline strings that never run, its numbers,
booleans and dates kept as such, its headings bold and frozen. Both write rows as they come: memory stays that of one
row for CSV and of one chunk for XLSX, whatever the number of rows.

## Status

| Part | State |
|---|---|
| The sheet, the formats and the registry of writers | Implemented and tested |
| CSV after RFC 4180, byte order mark, protection against formula injection | Implemented and tested |
| XLSX with ext-zip, inline strings, typed numbers, booleans and dates, frozen headings, limits of the format | Implemented and tested |
| The fuzz target of a cell's text and the benchmarks of both formats | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Writing a sheet](doc/sheets.md) | The sheet, its cells, the formats, a download from a controller. |
| [CSV](doc/csv.md) | The dialect, the byte order mark and the protection against formulas. |
| [XLSX](doc/xlsx.md) | The workbook, the kinds of cells, the limits and the temporary files. |
| [Extension points](doc/extension-points.md) | A writer of another format, and when the API is stable. |
| [Testing](doc/testing.md) | The package's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/data-table-export
```

```php
use Phil\DataTableExport\ExportWriters;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;

$rows = (function () use ($books): iterable {
    foreach ($books as $book) {
        yield [$book->title, $book->pages, $book->publishedOn];
    }
})();

$stream = fopen('php://output', 'w');
ExportWriters::defaults()->write(Format::Xlsx, new Sheet('Books', ['Title', 'Pages', 'Published'], $rows), $stream);
```

## Requirements

PHP 8.5 or later, with the extensions mbstring and zip.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
