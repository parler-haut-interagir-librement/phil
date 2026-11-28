<!-- phil-dev source-sha256=c774ce7e6ec11f7b29e294c0ce2f47647a417779283abcc76f2de1c11cfcb95c -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes ne font pas partie de l'API publique.

## Le modèle

| Type | Rôle |
|---|---|
| `Cell` | Une cellule : `type` et `value`. `text()`, `number()` (fini), `boolean()`, `date()` (minuit), `dateTime()`, `empty()` ; `from(mixed)` type une valeur PHP par son type PHP ; `isEmpty()`. Un texte vide est une cellule vide. |
| `CellType` | `Empty`, `Text`, `Number`, `Boolean`, `Date`, `DateTime`. |
| `Column` | Une colonne à écrire : `code`, `label` (le code quand null), `width` en caractères (1 à 255) ; `heading(Headings)`. |
| `Sheet` | Une feuille à écrire : `name` (1 à 31 caractères qu'un tableur permet), `rows` (tout itérable de tableaux, lu une seule fois), `columns` ; `nameFrom(string)` fait un nom valide ; `NAME_LENGTH` vaut 31. |
| `Workbook` | Les feuilles d'un fichier à écrire, au moins une, avec des noms distincts : `sheets` ; `single($name, $rows, $columns)`. |
| `Headings` | `Labels`, `Codes`, `None` : la ligne d'en-tête écrite. |
| `RowRange` | Les lignes de données écrites : `first` (à partir de 1), `last` (null pour toutes les lignes). |
| `WriteOptions` | `headings` (`Labels`), `range` (toutes les lignes), `guardFormulas` (true). |
| `Row` | Une ligne lue : `number` (à partir de 1), `cells` sans les vides à la fin ; `cell(int)`, `values()`. |
| `ReadLimits` | `maxFileBytes`, `maxEntries`, `maxPartBytes`, `maxSharedStringsBytes`, `maxRows`, `maxColumns`, `maxCells`, chacune de 1 ou plus. |
| `Format` | `Csv`, `Xlsx`, `Ods` : `extension()`, `mediaType()` ; `detect(string)` d'après une extension, un nom de fichier ou un type de média. |
| `Spreadsheets` | `writer(Format\|string)`, `reader(Format\|string)` et `writeFile(Workbook, string $path, ?Format, WriteOptions)`, avec les limites, le dialecte CSV et le répertoire temporaire qu'on lui a donnés. |

## Les contrats

| Type | Rôle |
|---|---|
| `Contract\SpreadsheetWriter` | `format()` ; `write(Workbook, resource $stream, WriteOptions)`. |
| `Contract\SpreadsheetReader` | `format()` ; `sheetNames(string $path)` ; `rows(string $path, ?string $sheet)`, un générateur de `Row`. |

## CSV

| Type | Rôle |
|---|---|
| `Csv\CsvDialect` | `delimiter` (`,`), `enclosure` (`"`), `lineEnding` (`\r\n` ou `\n`), `byteOrderMark` (true) ; `semicolon()`. |
| `Csv\CsvWriter` | Écrit une feuille en RFC 4180, directement dans le flux ; `text(Cell, bool $guardFormulas)`, le texte d'une cellule dans un fichier CSV. |
| `Csv\CsvReader` | Lit des enregistrements UTF-8 comme textes, dans les limites ; la marque d'ordre des octets est ignorée. |

## XLSX

| Type | Rôle |
|---|---|
| `Xlsx\XlsxWriter` | Écrit un classeur Office Open XML par des fichiers temporaires : `temporaryDirectory`, `rowLimit` (au plus `ROW_LIMIT`, 1 048 576) ; `COLUMN_LIMIT` 16 384. |
| `Xlsx\XlsxReader` | Lit les lignes d'un classeur, transitoire ou strict, nœud par nœud depuis l'archive. |
| `Xlsx\XlsxSheetParser` | Interne. Les lignes d'une partie de feuille de calcul. |
| `Xlsx\ExcelDate` | Interne. Les numéros de série des dates, dans les systèmes 1900 et 1904. |
| `Xlsx\NumberFormats` | Interne. Quels formats de nombre montrent une date, ou une date et heure. |
| `Xlsx\XlsxText` | Interne. Les textes d'un classeur échappés pour XML et `_xHHHH_`, et leur longueur. |
| `Xlsx\CellReference` | Interne. Les lettres d'une colonne. |

## ODS

| Type | Rôle |
|---|---|
| `Ods\OdsWriter` | Écrit un chiffrier OpenDocument, version 1.3, par des fichiers temporaires : `temporaryDirectory`, `rowLimit` (au plus `ROW_LIMIT`, 1 048 576) ; `COLUMN_LIMIT` 16 384, `MEDIA_TYPE`. |
| `Ods\OdsReader` | Lit les lignes d'un chiffrier ou d'un gabarit, nœud par nœud depuis l'archive. |
| `Ods\OdsContentParser` | Interne. Les tables de `content.xml`, leurs noms, et la table lue. |
| `Ods\OdsTableParser` | Interne. Les lignes d'une table, avec leurs répétitions. |
| `Ods\OdsText` | Interne. Un texte en paragraphes, tabulations et espaces conservés. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\SpreadsheetException` | L'interface de toute exception de la bibliothèque. |
| `Exception\InvalidSpreadsheet` | Un argument refusé : un nom de feuille, une valeur de cellule, une limite, un dialecte, un format, un flux. |
| `Exception\WriteFailed` | Un fichier qui ne peut pas être écrit : une valeur ou une taille que le format ne peut pas contenir, un flux ou un fichier temporaire qui refuse les octets. |
| `Exception\ReadRefused` | Un fichier refusé : illisible, au-delà d'une limite, dangereux ou mal formé. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Archive` | Une archive ZIP lue dans des limites : entrées à l'intérieur de la racine, parties lues à travers un budget d'octets. |
| `Internal\SafeXmlReader` | Une partie XML lue nœud par nœud, sans type de document, sans réseau et sans avertissements. |
| `Internal\ByteBudget`, `Internal\BudgetFilter` | Les octets qu'une partie peut encore donner, et le filtre de flux qui les compte. |
| `Internal\RowBuilder` | Les lignes d'une lecture : trous comblés, fins rognées, limites tenues. |
| `Internal\SheetRows` | La ligne d'en-tête et les lignes de données qu'un écrivain écrit. |
| `Internal\BufferedFile`, `Internal\Output` | Les fichiers temporaires, les archives et les flux des écrivains. |
| `Internal\FormulaGuard` | Les textes qu'un tableur exécuterait comme une formule. |
| `Internal\Values` | Les textes des nombres et des dates, et l'analyse des nombres et des dates ISO. |
