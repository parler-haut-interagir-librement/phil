# Writing a sheet

## The sheet

`Sheet` holds what is written: a title, the headings of the columns, and the rows.

```php
new Sheet(
    title: 'Books',                      // the worksheet's name in XLSX; CSV has none
    headings: ['Title', 'Pages', 'Read'], // the first row, none when empty
    rows: $rows,                         // any iterable of lists of cells, read once and in order
);
```

The rows are read once, as they are written. A generator that fetches its rows page by page keeps only one page in
memory, and the writer one row (CSV) or one chunk of 64 KiB (XLSX).

## The cells

| A cell holds | CSV writes | XLSX writes |
|---|---|---|
| `null` or `''` | an empty field | no cell |
| `true`, `false` | `1`, `0` | a boolean |
| an `int` or a finite `float` | the number, with a point | a number |
| `INF`, `-INF`, `NAN` | `INF`, `-INF`, `NAN` | the same, as text |
| a `string` | the text, guarded against formulas | an inline string |
| a `DateTimeInterface` | `2026-11-12`, or `2026-11-12 14:30:00` when it has a time | a date serial with a date format |
| a `BackedEnum` | its value | its value, as text or number |
| a `Stringable` | its string | its string, as text |

Any other value — an array, an object without `__toString()` — stops the writing with `ExportRefused`. A date is
written at its wall-clock time in its own zone: convert it before when the file is read in another.

## The formats

`Format` names the formats: `Format::Csv` and `Format::Xlsx`. `Format::named('xlsx')` reads one from a request,
refusing any other name; `mediaType()` gives the `Content-Type` of the file, and `fileName('books')` a safe name for
`Content-Disposition`, `books.xlsx`.

`ExportWriters` holds one writer per format. `ExportWriters::defaults()` gives CSV and XLSX with their defaults; build it
with your own writers to change them.

```php
$writers = new ExportWriters([new CsvWriter(separator: ';'), new XlsxWriter(rowLimit: 100_000)]);
$writers->formats();              // [Format::Csv, Format::Xlsx]
$writers->has(Format::Xlsx);      // true
$writers->write(Format::Csv, $sheet, $stream);
```

## A download from a Symfony controller

```php
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

$format = Format::named($request->query->getString('format', 'csv'));
$response = new StreamedResponse(static function () use ($writers, $format, $sheet): void {
    $writers->write($format, $sheet, fopen('php://output', 'w'));
});
$response->headers->set('Content-Type', $format->mediaType());
$response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $format->fileName('books')));

return $response;
```

A limit stops the writing with `ExportRefused` once the response has started: check what can be checked — the number of
rows — before streaming, or write to `php://temp` first when the file must be complete or nothing.
