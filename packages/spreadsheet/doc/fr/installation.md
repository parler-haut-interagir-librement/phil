<!-- phil-dev source-sha256=802af180388b89aa4b5dbf305f0faa63952efb54e892287337c24e766776b434 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `ext-zip`, qui écrit et lit les archives des fichiers XLSX et ODS ; `ext-xmlreader` et `ext-libxml`, qui lisent leurs
  parties XML nœud par nœud ; `ext-mbstring`, qui vérifie les textes et compte leurs caractères.

La bibliothèque n'a aucune autre dépendance.

## Installation

```sh
composer require phil/spreadsheet
```

La bibliothèque n'a aucun fichier de configuration. `Spreadsheets`, les écrivains et les lecteurs ne gardent rien entre
deux appels : une seule instance écrit ou lit autant de fichiers qu'on veut, dans un *worker* de longue durée aussi. Un
cadriciel enregistre `Spreadsheets` comme service, avec ses limites, son dialecte CSV et son répertoire temporaire, ou
le code le construit là où il en a besoin.

## Une première exportation

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

Un générateur garde la mémoire stable : chaque ligne est lue une seule fois, au moment où elle est écrite.
`writeFile()` écrit dans un chemin, au format que nomme son extension.

## Une première importation

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
