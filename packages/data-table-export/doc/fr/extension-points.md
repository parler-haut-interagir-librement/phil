<!-- phil-dev source-sha256=c0156ca402aa3203b6c5436f7034df04d56aa8d9afac86d0d7e4e21e970d3f1a -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## `ExportWriter`

`Phil\DataTableExport\Contract\ExportWriter` écrit une feuille dans un format. Implémentez-le pour changer la façon
dont un format est écrit, et donnez-le à `ExportWriters` à la place de l'écrivain du paquet.

| Méthode | Contrat |
|---|---|
| `format(): Format` | Le format qu'il écrit ; `ExportWriters` refuse deux écrivains d'un même format |
| `write(Sheet $sheet, resource $stream): void` | Écrit la feuille dans le flux ouvert, en lisant ses lignes une seule fois ; lance `ExportRefused` pour une feuille qui dépasse les limites du format, une cellule d'une sorte qu'il n'écrit pas, ou un flux qui refuse |

Un écrivain ne garde rien entre deux feuilles : une seule instance sert donc chaque requête d'un *worker*.

## Ce qui n'est pas un point d'extension

`Cells` et `SheetXml` sont internes : ils peuvent changer dans n'importe quelle version.
