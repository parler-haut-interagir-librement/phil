# Export

A table may offer its export: the rows it shows, filtered and sorted as they are shown, every page of them, in its
visible columns, as CSV or XLSX. The bundle writes them with `phil/data-table-export`, which it uses when it is
installed:

```sh
composer require phil/data-table-export
```

## Offering the export

```php
$table
    ->caption('books.caption')
    ->column(Column::text('title', 'books.title'))
    ->exportable('BOOK_EXPORT', maxRows: 5_000);
```

`exportable()` takes an optional voter attribute, asked beyond the table's own, and the most rows an export writes,
10,000 by default and 1,000,000 at most. Without phil/data-table-export, the declaration is kept and nothing is offered.

The table then shows a link per format beside its buttons, `Export as CSV` and `Export as XLSX`. A link carries the
table's sort, filters and columns, and its context with the seal the plain forms carry, so a browser cannot change
what the page gives the data source.

## What is written

| Column | Written as |
|---|---|
| Text | Its text |
| Number | A number |
| Date | A date, in the column's time zone, or PHP's when it names none |
| Enum | The translated label of its choice |
| Boolean | A boolean: `1` or `0` in CSV, a boolean cell in XLSX |
| Link | Its text |
| Block | Not written: what a block draws is not data |

The headings are the columns' translated labels, the title of an XLSX worksheet the table's caption. CSV starts with
the UTF-8 byte order mark, and a text that a spreadsheet would run as a formula is kept as text; see the documentation
of phil/data-table-export.

## The route

```yaml
# config/routes/phil_data_table.yaml, the routes of the plain forms
phil_data_table:
    resource: '@PhilDataTableBundle/config/routes.php'
    prefix: /_tables
```

The same routes file adds `phil_data_table_export`, `GET /{table}/export.{format}`, when phil/data-table-export is
installed. It is a GET that changes nothing, so it carries no CSRF token: the seal of the context opens the table, which
asks its voter, then the export's attribute; the sort, filters and columns are checked against the declaration as the
table's are. The file is sent as an attachment, `books.csv`, with `Cache-Control: no-store, private` and
`X-Content-Type-Options: nosniff`.

## Memory and limits

The rows are fetched from the data source 500 at a time while the file is written, so memory holds one page whatever
their number. Before anything is written, the first page says how many rows match: past `maxRows`, the export is
refused in a `422` that asks the person to narrow the table with its filters, through the template
`@PhilDataTable/table/stopped.html.twig`. An unknown format, or a table that offers no export, is a `404`; a seal that
does not match, or a voter that refuses, a `403`.
