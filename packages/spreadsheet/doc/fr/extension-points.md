<!-- phil-dev source-sha256=94e2692b586b88b734ef09ccee32d8a84485fa2028633e6823474214e2352f39 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Écrire un autre format | Implémentez `Contract\SpreadsheetWriter` : `format()` et `write(Workbook, $stream, WriteOptions)` |
| Lire un autre format | Implémentez `Contract\SpreadsheetReader` : `format()`, `sheetNames(string $path)` et `rows(string $path, ?string $sheet)` |
| Choisir un écrivain ou un lecteur dans un service | Typez la dépendance par le contrat, et laissez `Spreadsheets` ou le conteneur le fournir |
| Lire un fichier CSV d'une autre forme | Passez un `CsvDialect` à `Spreadsheets`, à `CsvWriter` ou à `CsvReader` |
| Borner les lectures d'une application | Passez `ReadLimits` à `Spreadsheets` ou à un lecteur |
| Mettre ailleurs les fichiers temporaires du XLSX et de l'ODS | Passez un répertoire à `Spreadsheets`, à `XlsxWriter` ou à `OdsWriter` |

Une implémentation des contrats ne garde aucun état entre deux appels, comme celles du paquet, afin qu'une seule
instance serve un *worker* de longue durée.

Un écrivain lit les lignes de chaque feuille une seule fois, dans l'ordre, et applique les options : la ligne d'en-tête
des colonnes, la plage de lignes — sans lire aucune ligne après la dernière — et la protection des formules. Un lecteur
donne les lignes qui contiennent une cellule, avec leur numéro, sans les cellules vides à leur fin, et refuse par une
`ReadRefused` un fichier qui dépasse ses limites.

`Format` énumère les formats que `Spreadsheets` connaît ; un écrivain ou un lecteur d'un autre format s'emploie
directement, ou par la fabrique propre à l'application.

Les classes de l'espace de noms `Internal`, et celles marquées `@internal` dans les espaces de noms `Xlsx` et `Ods`, ne
font pas partie de l'API : elles changent sans préavis.
