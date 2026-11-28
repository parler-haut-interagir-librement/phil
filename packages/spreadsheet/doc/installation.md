# Installation

## Requirements

- PHP 8.5 or later.
- `ext-zip`, which writes and reads the archives of XLSX and ODS files; `ext-xmlreader` and `ext-libxml`, which read
  their XML parts node by node; `ext-mbstring`, which checks texts and counts their characters.

The library has no other dependency.

## Installing

```sh
composer require phil/spreadsheet
```

The library has no configuration file. `Spreadsheets`, the writers and the readers keep nothing between two calls:
one instance writes or reads any number of files, in a long-running worker as well. A framework registers
`Spreadsheets` as a service, with its limits, its CSV dialect and its temporary directory, or the code builds it where
it needs it.

## A first export

```php
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Workbook;

$rows = (function () use ($orders): iterable {
    foreach ($orders as $order) {
        yield [$order->sku, $order->quantity, $order->total, Cell::date($order->placedOn), $order->note];
    }
})();

$workbook = Workbook::single('Orders', $rows, [
    new Column('sku', 'Product code', 14),
    new Column('quantity', 'Quantity'),
    new Column('total', 'Total'),
    new Column('placed_on', 'Placed on'),
    new Column('note', 'Note', 40),
]);

$stream = fopen('php://output', 'w');
new Spreadsheets()->writer(Format::Ods)->write($workbook, $stream);
```

A generator keeps memory flat: each row is read once, when it is written. `writeFile()` writes to a path in the format
its extension names.

## A first import

```php
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Spreadsheets;

$spreadsheets = new Spreadsheets();
$reader = $spreadsheets->reader($uploadedFileName); // the extension or the media type chooses the reader

try {
    foreach ($reader->rows($uploadedPath) as $row) {
        $code = $row->cell(0)->value; // a string: '00123' keeps its zeros
    }
} catch (ReadRefused $refused) {
    // a file over a limit, unsafe, or not well formed: nothing of it is trusted
}
```
