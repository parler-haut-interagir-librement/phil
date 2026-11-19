<!-- phil-dev source-sha256=bc6f57239c6940bb81e13b8b7caaa1136a515505aedd836564c1a21ddebbc45d -->
# Documents

Un `Document` contient ses blocs dans l'ordre de lecture, l'étiquette BCP 47 de sa langue, qui choisit le code braille,
et un titre, écrit dans les métadonnées d'un fichier PEF.

```php
new Document($blocks, 'fr-CA', 'The title');
```

## Les blocs

| Bloc | Ce que c'est |
|---|---|
| `Heading($text, $level)` | Un titre de niveau 1 à 6. |
| `Paragraph($text)` | Un paragraphe ; un saut de ligne de son texte, `\n`, commence une nouvelle ligne. |
| `ListBlock($items, $ordered, $start)` | Une liste de `ListItem($text, $children)`, chacun avec la liste imbriquée sous lui ; une liste ordonnée numérote ses éléments à partir de `$start`. |
| `Table($rows, $headerRow, $caption)` | Un tableau : ses rangées, chacune une liste des textes de ses cellules, si la première rangée contient les titres de colonnes, et une légende. |
| `PageBreak` | Le bloc suivant commence une nouvelle page braille. |
| `SectionBreak` | Le bloc suivant commence une nouvelle section, sur une nouvelle page : un `section` d'un fichier PEF. |
| `PrintPage($number)` | Une page de l'édition imprimée commence ici, son numéro tel qu'imprimé, comme `12` ou `iv`. |

Chaque bloc implémente `Contract\Block`. La mise en page connaît les blocs ci-dessus ; un bloc d'une autre classe est
omis.

Les textes sont du texte brut. Les espaces blancs et les caractères de contrôle se réduisent à une seule espace, et un
texte qui ne contient que des espaces blancs ne donne rien.

## Un document UJO

`UjoReader` lit dans un document un document UJO (Universal JSON Output) de `phil/ujo` :

```php
use Phil\BrailleExport\Ujo\UjoReader;
use Phil\Ujo\Ujo;

$document = new UjoReader()->read(Ujo::decode($json), 'fr', 'The title');
```

| Bloc UJO | Bloc |
|---|---|
| `header` | `Heading`, son niveau gardé entre 1 et 6 |
| `paragraph` | `Paragraph`, son HTML lu comme du texte, un `<br>` un saut de ligne |
| `list` | `ListBlock`, éléments imbriqués et début d'une liste ordonnée conservés |
| `table` | `Table`, `withHeadings` comme rangée de titres, sa légende conservée |
| `quote` | Un paragraphe pour la citation, puis un pour sa légende |
| `pageBreak` | `PageBreak` |
| `delimiter` | Rien : un lecteur de braille n'en a pas besoin |
| Tout autre | Les paragraphes de son texte brut, tel que `PlainText` de `phil/ujo` l'écrit ; rien s'il n'en a pas |

Un `PlainText` donné au constructeur change ou ajoute le texte d'un type, comme le fait `PlainText::with()`.
