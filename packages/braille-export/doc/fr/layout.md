<!-- phil-dev source-sha256=12086c1c0563e5ac8982300c95475d70e9164000842caee64b622778335ab231 -->
# Mise en page

`BrailleExporter::layout()` traduit les textes d'un document en un seul lot, puis les coupe en lignes et en pages :

```php
$book = new BrailleExporter($translator, new PageFormat(cellsPerLine: 32, linesPerPage: 26))
    ->layout($document, Contraction::Contracted);
```

Le `Book` qu'elle donne contient des volumes, chacun de sections, chacune de pages, chacune de rangées de braille
Unicode, numéros de page compris, aucune rangée ne se terminant par une cellule blanche. Les écrivains des
[Formats de sortie](output-formats.md) le lisent.

## Le format de page

| Argument de `PageFormat` | Valeur par défaut | Sens |
|---|---|---|
| `cellsPerLine` | 40 | Les cellules d'une ligne, de 12 à 200. |
| `linesPerPage` | 25 | Les lignes d'une page, de 3 à 200. |
| `braillePageNumbers` | `true` | Si le numéro de page braille termine la dernière ligne de chaque page. |
| `printPageNumbers` | `true` | Si la page imprimée termine la première ligne de chaque page, et si une ligne montre où une page commence. |
| `pagesPerVolume` | `null` | Les pages d'un volume ; un seul volume sans cet argument. |
| `duplex` | `false` | Si les pages sont embossées des deux côtés, comme le dit un fichier PEF. |
| `maxPages` | 10,000 | Le plus grand nombre de pages que peut remplir un document. |

40 cellules sur 25 lignes, c'est la page d'une imprimante braille sur du papier de 11 sur 11,5 pouces ; le format du
livre est celui sur lequel son imprimante est réglée.

## Les blocs

Les cellules se comptent à partir de 1, à la marge de gauche.

| Bloc | Première ligne | Lignes suivantes | Autour |
|---|---|---|---|
| Paragraphe | cellule 3 | cellule 1 | — |
| Saut de ligne dans un paragraphe | cellule 1 | cellule 1 | — |
| Titre de niveau 1 | centré, au moins 3 cellules blanches de chaque côté | centré | une ligne blanche avant, sauf s'il commence une page, et après |
| Titre de niveau 2 | cellule 5 | cellule 5 | une ligne blanche avant, sauf s'il commence une page |
| Titre de niveau 3 à 6 | cellule 7 | cellule 7 | une ligne blanche avant, sauf s'il commence une page |
| Élément de liste de niveau 1 | cellule 1 | cellule 3 | — |
| Chaque niveau de liste de plus | 2 cellules plus loin | 2 cellules plus loin | — |
| Légende de tableau | cellule 3 | cellule 1 | — |
| Rangée de tableau | cellule 1 | cellule 3 | — |

Un titre passe à la page suivante quand la page ne peut pas contenir aussi la ligne qui le suit. Une liste ordonnée
écrit le numéro de chaque élément, suivi d'un point, avant son texte. L'imbrication décale une liste jusqu'au neuvième
niveau ; les niveaux plus profonds y restent, et une ligne garde toujours deux cellules pour son texte.

Un tableau ne peut pas garder ses colonnes sur une ligne braille : chaque rangée devient donc une seule entrée, ses
cellules séparées par des points-virgules, chacune précédée du titre de sa colonne et d'un deux-points quand la première
rangée contient les titres — `Name: Ann; Age: 12` en anglais, `Nom : Ann; Âge : 12` en français. Une cellule vide est
omise.

## La coupure des lignes

Un mot qui ne tient pas passe entier à la ligne suivante. Un mot plus long qu'une ligne entière est coupé :

- après un trait d'union qu'il contient, le dernier qui tient, quand une cellule le précède et une autre le suit : la
  ligne se termine par le trait d'union du mot et aucun second n'est ajouté ;
- sinon avant la dernière cellule de la ligne, avec le trait d'union des points 3-6 ajouté, jamais juste après une
  cellule qui en annonce une autre — un signe de majuscule, un signe de nombre, l'indicateur de degré 1 ou la première
  cellule d'un signe composé.

Un mot qui contient un trait d'union est aussi coupé après lui quand sa première partie tient à la fin d'une ligne.

## Les numéros de page

Le numéro de page braille, tel que le traducteur écrit le nombre, termine la dernière ligne de chaque page avec au
moins trois cellules blanches avant lui ; la ligne lui laisse cette place. Les numéros sont demandés au traducteur par
lots de 64 pages.

Un repère `PrintPage` fixe la page imprimée. Son numéro termine la première ligne de chaque page braille tant qu'il
dure, avec la même place. Quand une page imprimée commence au milieu d'une page braille, une ligne de points 3-6 montre
où, terminée par une cellule blanche et le nouveau numéro ; quand cette ligne serait la dernière de sa page, la page
braille suivante commence à la place, avec le nouveau numéro à la fin de sa première ligne. Un numéro de page imprimée
occupe au plus le tiers d'une ligne.

## Pages, sections et volumes

`PageBreak` commence une nouvelle page et `SectionBreak` une nouvelle section, jamais une page vide. Une ligne blanche
ne commence jamais une page. Un document vide est une page vide. Avec `pagesPerVolume`, les pages sont reliées en
volumes de ce nombre de pages, une section se poursuivant dans le volume suivant quand elle ne se termine pas avec son
volume. Les pages sont numérotées à partir de 1 dans tout le livre.
