<!-- phil-dev source-sha256=d0c39cb541fcc01c1cbbe4cb090dc9c9c327041842221c9a37f923c5c3ed7c9c -->
# Installation

```sh
composer require phil/data-table-export
```

Le paquet exige PHP 8.5 ou une version plus récente, avec les extensions mbstring et zip. Il n'a aucune autre
dépendance et aucune configuration : une application construit ses écrivains là où elle en a besoin, ou enregistre
`ExportWriters` comme service.

```php
use Phil\DataTableExport\ExportWriters;

$writers = ExportWriters::defaults('/var/app/tmp'); // CSV and XLSX, XLSX writing its temporary files there
```

`phil/data-table-bundle` utilise le paquet quand il est installé, pour offrir l'export d'un tableau.
