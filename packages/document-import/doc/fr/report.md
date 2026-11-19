<!-- phil-dev source-sha256=3c8aa130f665b1595f96c72997ebde48a66daa4345fc1083bd1f8ecd688d7823 -->
# Le rapport

`Imported::$report` dit ce que le document avait et que sa forme UJO ne garde pas.

```php
foreach ($imported->report->omissions() as $omission) {
    printf("%s (%s): %d\n", $omission->what->description(), $omission->detail, $omission->count);
}

$imported->report->isEmpty();
$imported->report->count();                    // everything left out
$imported->report->count(Omitted::Comment);    // one kind
$imported->report->toArray();                  // rows for JSON: what, detail, count, description
$imported->report->summary();                  // one sentence per kind, its details counted
$imported->report->toBlock('Not imported');    // a phil/ujo warning block of those sentences
$imported->documentWithReport();               // the document with that block at its end
```

Chaque `Omission` est une sorte et un détail, avec le nombre de fois où elle a été rencontrée. Les sortes viennent dans
l'ordre de leur première rencontre, et les détails d'une sorte à sa suite.

| `Omitted` | Valeur | Détails |
|---|---|---|
| `Comment` | `comment` | |
| `TrackedChange` | `tracked-change` | `deletion` |
| `HeaderFooter` | `header-footer` | `header`, `footer` |
| `TextBox` | `text-box` | |
| `Drawing` | `drawing` | `shape`, `chart` |
| `Equation` | `equation` | |
| `EmbeddedObject` | `embedded-object` | |
| `Image` | `image` | `missing`, `linked file`, `same name`, le type de média d'une image qu'un navigateur n'affiche pas, `application/octet-stream` pour une adresse `data:` qui n'est pas une image ; pour RTF, la sorte d'image (`emfblip`, `wmetafile`…) ; pour PDF, `pdf colour space`, `pdf JPXDecode` et les autres filtres d'image, `inline` ; pour un export, `not fetched` |
| `SvgContent` | `svg-content` | La valeur de la `RemovalReason` de `phil/svg-sanitizer` : `denied_element`, `event_handler`, `external_reference`… |
| `NestedTable` | `nested-table` | |
| `MergedCell` | `merged-cell` | |
| `InternalLink` | `internal-link` | |
| `HiddenText` | `hidden-text` | |
| `Formatting` | `formatting` | `colour`, `font`, `size`, `symbol` |
| `Macro` | `macro` | |
| `Element` | `element` | Le nom de l'élément HTML |
| `Style` | `style` | Le nom du style de paragraphe que la [table des styles](styles.md) laisse de côté |
| `Block` | `block` | Pour un export : le type du bloc qu'un document de traitement de texte ne peut pas accueillir, `link` |
| `Annotation` | `annotation` | Pour un PDF : `form`, et le sous-type de l'annotation (`widget`, `stamp`, `fileattachment`…) |

`Omitted::description()` donne une phrase en anglais pour chaque sorte ; une application qui affiche le rapport dans
une autre langue traduit d'après la valeur.

## Rendre le rapport avec le document

Une personne qui lit un texte importé devrait savoir ce qu'il a perdu. `Report::summary()` donne une phrase par sorte,
ses détails avec leur nombre (`Colours, fonts and sizes are not imported: colour (2), font (1).`), et
`Report::toBlock()` place ces phrases, une par ligne et échappées, dans un bloc `warning` dont le titre est donné.
`Imported::documentWithReport()` donne le document avec ce bloc à la fin, sous un identifiant qu'aucun autre bloc
n'utilise, et le document tel quel quand rien n'a été laissé de côté :

```php
$json = new Ujo()->encode($imported->documentWithReport('Not imported from the manuscript'));
```

Un rapport mesure la fidélité, ce n'est pas une erreur : une importation avec des omissions a réussi.
