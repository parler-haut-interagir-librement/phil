# Installation

```sh
composer require phil/data-table-export
```

The package needs PHP 8.5 or later, with the extensions mbstring and zip. It has no other dependency and no
configuration: an application builds its writers where it needs them, or registers `ExportWriters` as a service.

```php
use Phil\DataTableExport\ExportWriters;

$writers = ExportWriters::defaults('/var/app/tmp'); // CSV and XLSX, XLSX writing its temporary files there
```

`phil/data-table-bundle` uses the package when it is installed, to offer the export of a table.
