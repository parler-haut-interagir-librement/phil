<!-- phil-dev source-sha256=e0d1bcd064c18fddc058e55f1e4bd8308ea19231ab204681308e9773ceb883b0 -->
# Lecture

## Les lecteurs

Chaque lecteur implémente `Contract\SpreadsheetReader` :

- `sheetNames(string $path)` : les noms des feuilles du fichier, dans l'ordre ;
- `rows(string $path, ?string $sheet = null)` : un générateur des `Row` d'une feuille, la première quand aucun nom
  n'est donné.

`Spreadsheets::reader()` donne le lecteur d'un `Format`, d'une extension, d'un nom de fichier ou d'un type de média,
réglé avec les `ReadLimits` et le dialecte CSV de la fabrique. L'extension ou le type de média qu'un navigateur a
envoyé ne fait que choisir le lecteur : le lecteur vérifie les octets, et un fichier qui n'est pas ce qu'il prétend
est refusé.

Le fichier est ouvert quand la première ligne est demandée, et lu à mesure que les lignes sont demandées : la mémoire
reste celle d'une ligne, et une limite atteinte à mi-chemin lance alors une `ReadRefused`. Cessez de demander des
lignes et le fichier est fermé.

## Les lignes

Une `Row` a son `number` dans la feuille, à partir de 1, comme le tableur l'affiche, et ses `cells`, une liste de
`Cell`. Les cellules vides entre deux cellules y sont ; les cellules vides à la fin d'une ligne n'y sont pas ; une
ligne sans aucune cellule restante n'est jamais donnée, si bien que les numéros des lignes données peuvent avoir des
trous. `cell($index)` donne la cellule d'une colonne, à partir de 0, une cellule vide au-delà de la fin ; `values()`
donne les valeurs des cellules, null pour une cellule vide.

## Les types lus

### CSV

`CsvReader` lit de l'UTF-8 avec son `CsvDialect` — le séparateur et le délimiteur de champ ; la marque d'ordre des
octets est ignorée quoi que dise le dialecte. Un fichier CSV n'a pas de types : **chaque cellule lue est un texte**, et
un champ vide est une cellule vide. Le code qui le lit énonce les types, comme il le fait à l'écriture. Le numéro d'une
ligne compte les enregistrements, lignes vides comprises. Le fichier est une seule feuille, nommée d'après le fichier
sans son extension. Un enregistrement qui n'est pas en UTF-8 est refusé.

### XLSX

`XlsxReader` lit les classeurs du schéma transitoire et du schéma strict :

- un texte partagé, un texte en ligne, le texte qu'une formule a enregistré et une erreur comme `#DIV/0!` sont des
  textes, avec les échappements `_xHHHH_` du format décodés et les segments phonétiques omis ;
- un nombre est un entier quand il est écrit comme tel et qu'il tient, un flottant sinon ;
- un nombre sous un format de date est une date, ou une date et heure quand le format montre une heure, en UTC, à la
  milliseconde près ; le système de dates 1900 — avec son 29 février 1900 qui n'a jamais existé — et celui de 1904 sont
  tous deux lus. Une cellule de date du schéma strict, écrite en ISO 8601, est lue de la même façon ;
- un booléen est un booléen ;
- une formule n'est pas exécutée : la cellule donne la valeur que le tableur a enregistrée avec elle.

### ODS

`OdsReader` lit les chiffriers et leurs gabarits :

- une cellule de chaîne est un texte, ses paragraphes joints par des sauts de ligne, avec ses espaces, ses tabulations
  et ses sauts de ligne ; les annotations et les dessins d'une cellule sont omis ;
- une cellule flottante, de pourcentage ou de devise est un nombre ;
- une cellule de date est une date, ou une date et heure quand elle a une heure ;
- une cellule booléenne est un booléen ;
- une heure du jour est le texte qu'elle montre ;
- une ligne ou une cellule qu'un tableur répète donne autant de lignes ou de cellules ; les lignes et cellules vides
  répétées, qu'un tableur écrit jusqu'à la fin de la feuille, ne font que déplacer le compte.

## Les limites

`ReadLimits` borne chaque lecture ; un fichier qui dépasse une limite est refusé, jamais lu en partie :

| Limite | Valeur par défaut | Ce qu'elle borne |
|---|---|---|
| `maxFileBytes` | 256 MiB | la taille du fichier, fichier CSV ou archive |
| `maxEntries` | 1 000 | les entrées d'une archive |
| `maxPartBytes` | 1 GiB | les octets d'une partie d'archive une fois décompressée, comptés à mesure qu'ils sont lus |
| `maxSharedStringsBytes` | 128 MiB | la mémoire que prennent les textes partagés d'un classeur XLSX, chacun compté avec 32 octets de plus |
| `maxRows` | 1 048 576 | le plus haut numéro d'une ligne qui a une cellule |
| `maxColumns` | 16 384 | les colonnes d'une ligne |
| `maxCells` | 50 000 000 | les cellules données par une lecture, les vides à l'intérieur des lignes comprises |

Abaissez-les pour les fichiers que les gens téléversent : une importation de quelques milliers de lignes demande
quelques mégaoctets, pas 256.

```php
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Spreadsheets;

$spreadsheets = new Spreadsheets(new ReadLimits(maxFileBytes: 8 * 1024 * 1024, maxRows: 20_000, maxColumns: 100));
```

Les textes partagés d'un classeur XLSX sont la seule partie qu'un lecteur garde en entier, puisque les lignes s'y
réfèrent par index : `maxSharedStringsBytes` borne cette mémoire.
