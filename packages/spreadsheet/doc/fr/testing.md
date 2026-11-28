<!-- phil-dev source-sha256=01184864d4476887ddb869dfa39cb6bdd6f12172ed159139f35be6b6943fe413 -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit dans `tests/Unit`, exécutés depuis la racine du monodépôt avec `composer test`, ou
seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau, dans un dossier temporaire qui leur est propre et que
chaque test supprime.

- Les fichiers que chaque écrivain écrit sont relus par le lecteur du même format, cellule par cellule, dans
  `tests/Unit/SpreadsheetsTest`.
- Les parties XML qu'écrivent les écrivains XLSX et ODS sont analysées avec `Dom\XMLDocument` et vérifiées pour leur
  structure : les entrées de l'archive et leur ordre, les types de contenu, les relations, les feuilles, les styles, le
  manifeste, et le `mimetype` stocké en premier.
- Les lecteurs sont testés sur des archives écrites à la main dans `tests/Support/Workbooks` : textes partagés, styles
  et formats de date, système de dates 1904, classeur à la racine de son archive, lignes et cellules ODS répétées, et
  les fichiers qu'ils refusent — entrées hors de la racine, type de document avec une entité externe, partie au-delà de
  sa taille, partie chiffrée, partie tronquée.

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Les cibles de fuzzing

Les trois lecteurs ont chacun une cible de fuzzing, exécutée par `composer fuzz`, avec de petites limites :

| Cible | Ce qu'elle lit | Germes |
|---|---|---|
| `spreadsheet-csv` | `CsvReader` sur des octets quelconques | `tests/Fuzz/corpus/spreadsheet-csv` |
| `spreadsheet-xlsx-sheet` | l'analyseur de feuille XLSX sur du XML quelconque, avec deux textes partagés et deux styles de date | `tests/Fuzz/corpus/spreadsheet-xlsx-sheet` |
| `spreadsheet-ods-content` | l'analyseur de contenu ODS sur du XML quelconque | `tests/Fuzz/corpus/spreadsheet-ods-content` |

Chaque lecture donne des lignes ou lance une `ReadRefused` : rien d'autre n'en sort, aucun avertissement n'est levé, et
chaque ligne donnée reste dans les limites, sa dernière cellule non vide.

```sh
composer fuzz -- spreadsheet-xlsx-sheet
```

## Les bancs d'essai

`tests/Benchmark/SpreadsheetBench` écrit 100 000 lignes de dix cellules — des textes, un code de produit, un entier, un
flottant, un booléen, une date et des cellules vides — en CSV, en XLSX et en ODS, puis les relit :

```sh
composer bench -- --filter=SpreadsheetBench
```

Le pic de mémoire est celui d'une ligne et d'un fragment, le même pour 1 000 lignes que pour 100 000. Sur un portable
de 2026, avec PHP 8.5 et sans OPcache, les durées sont d'environ 1,3 s pour écrire le CSV, 3,6 s le XLSX et 1,9 s
l'ODS, et 0,9 s pour lire le CSV, 4,7 s le XLSX et 4,9 s l'ODS, à un pic de 5,3 MiB.
