<!-- phil-dev source-sha256=7bf2c334c13681ccdd9f5af100169edf225fc2809126f749aca2807e04995f16 -->
# Écriture

## Le classeur

Un `Workbook` contient une `Sheet` ou plusieurs, dans l'ordre, avec des noms distincts quelle que soit leur casse. Une
feuille a :

- un **nom** de 1 à 31 caractères, sans `[ ] : * ? / \` ni caractère de contrôle, sans apostrophe à l'une ou l'autre
  extrémité, et qui n'est pas `History`, que les tableurs gardent pour eux. `Sheet::nameFrom($title)` fait un nom
  valide à partir de n'importe quel titre : les caractères refusés deviennent des espaces, il est coupé à 31
  caractères, et vaut `Sheet1` quand il ne reste rien ;
- ses **lignes**, tout `iterable` de tableaux, lu une seule fois, dans l'ordre, pendant que le fichier s'écrit. Un
  générateur garde la mémoire d'une ligne ; les clés d'une ligne sont ignorées ;
- ses **colonnes**, une liste de `Column` : le `code` qu'un programme lit, le `label` qu'une personne lit, et une
  `width` en caractères, de 1 à 255. Les colonnes donnent la ligne d'en-tête et les largeurs ; une feuille sans
  colonnes n'a ni l'une ni les autres.

`Workbook::single($name, $rows, $columns)` est le classeur d'une seule feuille.

## Les cellules et leurs types

Une `Cell` a un `CellType` et une valeur. Le type est énoncé par le code, jamais deviné d'après le texte :

| Type | Valeur | Fait par |
|---|---|---|
| `Text` | une chaîne | `Cell::text()`, ou toute chaîne PHP |
| `Number` | un entier, ou un flottant fini | `Cell::number()`, ou tout entier ou flottant PHP |
| `Boolean` | un booléen | `Cell::boolean()`, ou tout booléen PHP |
| `Date` | un `DateTimeImmutable` à minuit | `Cell::date()` |
| `DateTime` | un `DateTimeImmutable` | `Cell::dateTime()`, ou tout `DateTimeInterface` |
| `Empty` | null | `Cell::empty()`, null, ou une chaîne vide |

Une ligne peut contenir des cellules, des valeurs PHP, ou les deux : `Cell::from()` transforme une valeur PHP en
cellule par son seul type PHP, si bien que la chaîne `'00123'` reste un texte avec ses zéros, et `'42'` reste un texte
aussi. Toute autre valeur — un tableau, un objet qui n'est pas une date — est refusée par une `InvalidSpreadsheet`,
tout comme un nombre infini et `NAN`.

Une date est écrite à son heure locale, son fuseau horaire abandonné : les tableurs n'ont pas de fuseaux horaires.
Convertissez une date dans le fuseau que le lecteur du fichier attend avant de l'écrire.

## Les options

`WriteOptions` règle, pour chaque feuille du classeur :

- `headings` : `Headings::Labels` (la valeur par défaut) écrit le libellé de chaque colonne, ou son code quand elle n'en
  a pas ; `Headings::Codes` écrit les codes ; `Headings::None` n'écrit aucune ligne d'en-tête ;
- `range` : une `RowRange` des lignes de données écrites, comptées à partir de 1 sans la ligne d'en-tête, les deux
  bouts compris. Les lignes après la dernière ne sont jamais lues dans leur itérable, si bien qu'une plage sur un
  générateur arrête la requête qui le nourrit ;
- `guardFormulas` : true par défaut. Voir [Sécurité](security.md).

```php
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\RowRange;
use Phil\Spreadsheet\WriteOptions;

$writer->write($workbook, $stream, new WriteOptions(Headings::Codes, new RowRange(1, 500)));
```

## Les écrivains

Chaque écrivain implémente `Contract\SpreadsheetWriter` : `format()`, et `write(Workbook, $stream, WriteOptions)`, qui
écrit dans un flux ouvert et le laisse ouvert. `Spreadsheets::writer()` donne l'écrivain d'un `Format`, d'une
extension, d'un nom de fichier ou d'un type de média ; `Spreadsheets::writeFile()` écrit dans un chemin.

### CSV

`CsvWriter` écrit du RFC 4180, directement dans le flux, ligne par ligne. Son `CsvDialect` règle le séparateur (`,`),
le délimiteur de champ (`"`), la fin de ligne (`\r\n` ou `\n`) et la marque d'ordre des octets UTF-8 dont les tableurs
ont besoin pour lire les accents (écrite par défaut). `CsvDialect::semicolon()` est le dialecte des tableurs réglés sur
une langue qui écrit les décimales avec une virgule.

Un fichier CSV n'a ni types ni plus d'une feuille : un classeur de plus d'une feuille est refusé. Un nombre s'écrit
`12.5`, un booléen `TRUE` ou `FALSE`, une date `2026-11-21`, une date et heure `2026-11-21 14:30:00`, avec sa fraction
de seconde quand elle en a une. Un échec à mi-chemin laisse les lignes déjà écrites dans le flux.

### XLSX

`XlsxWriter` écrit un classeur Office Open XML : les lignes de chaque feuille vont dans un fichier temporaire par
fragments de 64 KiB, puis les parties sont zippées avec ext-zip et copiées dans le flux. Le flux ne reçoit rien quand
l'écriture échoue, et les fichiers temporaires sont supprimés dans les deux cas ; ils vont dans le répertoire donné à
l'écrivain, ou dans celui du système.

- Un texte est une chaîne en ligne, qu'un tableur n'exécute jamais comme une formule ; les caractères que le XML ne peut
  pas contenir sont écrits comme les échappements `_xHHHH_` du format.
- Un nombre et un booléen gardent leur nature.
- Une date est un numéro de série formaté `yyyy-mm-dd`, une date et heure un numéro formaté `yyyy-mm-dd hh:mm:ss`. Le
  format écrit les jours du 1900-03-01 au 9999-12-31 : une date hors de cette plage est refusée.
- La ligne d'en-tête est en gras et figée ; les colonnes qui ont une largeur la reçoivent.
- Une feuille contient au plus 1 048 576 lignes, ligne d'en-tête comprise, et 16 384 colonnes ; un texte au plus
  32 767 caractères. Au-delà, le classeur est refusé par une `WriteFailed`. Le second argument de l'écrivain abaisse le
  nombre maximal de lignes d'une feuille, pour borner une exportation.

Chaque entrée de l'archive porte la même date, si bien que le même classeur donne les mêmes octets.

### ODS

`OdsWriter` écrit un chiffrier OpenDocument, version 1.3, de la même façon : le contenu va dans un fichier temporaire
par fragments, puis l'archive est zippée — son `mimetype` en premier et stocké, comme le format l'exige — et copiée
dans le flux.

- Un texte est une cellule de chaîne, un paragraphe par ligne, avec ses tabulations et les espaces que le XML
  réduirait conservés ; les caractères de contrôle que le XML ne peut pas contenir deviennent U+FFFD.
- Un nombre est une cellule flottante, un booléen une cellule booléenne, une date une cellule de date formatée
  `yyyy-mm-dd` ou `yyyy-mm-dd hh:mm:ss`, à n'importe quelle année.
- La ligne d'en-tête est en gras ; les colonnes qui ont une largeur la reçoivent, à 0,2 cm par caractère.
- Une feuille contient au plus 1 048 576 lignes, ligne d'en-tête comprise — moins quand le second argument de
  l'écrivain le dit — et 16 384 colonnes.

## Envoyer un fichier depuis un contrôleur

Un écrivain écrit dans n'importe quel flux : `php://output` dans une réponse en continu, ou un flux temporaire remis à
une réponse de fichier.

```php
use Symfony\Component\HttpFoundation\StreamedResponse;

return new StreamedResponse(static function () use ($spreadsheets, $workbook): void {
    $spreadsheets->writer(Format::Xlsx)->write($workbook, fopen('php://output', 'w'));
}, 200, [
    'Content-Type' => Format::Xlsx->mediaType(),
    'Content-Disposition' => 'attachment; filename="answers.xlsx"',
]);
```
