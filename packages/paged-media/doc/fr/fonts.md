<!-- phil-dev source-sha256=03105fa4ab948bc16e8c0e16b709525a5a400cd986d16b1f58f1487ea489c2e9 -->
# Polices

Un texte est écrit et mesuré dans une `Font` : l'une des polices standard que possède tout lecteur PDF, ou une police
TrueType que le document intègre.

## Polices standard

`StandardFont::Helvetica` et `StandardFont::HelveticaBold` n'intègrent rien : un document écrit avec elles reste petit.
Elles sont encodées en Windows-1252, qui contient les accents du français et des autres langues occidentales. Un
caractère absent de cet encodage ne peut pas être imprimé : le signe moins, les tirets et les espaces fines et
insécables, courants dans les prix et les étiquettes, s'impriment comme leur plus proche équivalent, et tout autre
caractère comme `?`.

Chacune a ses propres largeurs : un total en gras aligné à droite tombe donc exactement en place.

## Polices TrueType

```php
$font = TrueTypeFont::fromFile('/path/to/Font-Regular.ttf');
```

Une `TrueTypeFont` imprime tout caractère que contient son fichier. Le document l'intègre **réduite aux glyphes qu'il
imprime** — les glyphes utilisés, les composantes des glyphes composites et le glyphe manquant — sous leurs propres
numéros, avec une correspondance vers Unicode pour que le texte puisse être copié et cherché. Une police de 380 Ko qui
imprime une page de texte ajoute environ 15 Ko.

Un caractère absent de la police s'imprime comme le glyphe manquant de la police, habituellement une boîte vide.

Seuls les contours TrueType sont lus : une police OpenType à contours PostScript (`CFF`) est refusée avec
`InvalidFont`. Le crénage et les ligatures ne sont pas appliqués.

## Licences

La table `OS/2` d'une police dit ce que sa licence permet, et la bibliothèque s'y conforme :

| La police indique | La bibliothèque |
|---|---|
| Intégration installable, modifiable, ou pour impression et aperçu | Intègre la police réduite aux glyphes utilisés |
| Aucun sous-ensemble | Intègre toute la police, sans étiquette de sous-ensemble |
| Intégration sous licence restreinte, ou en bitmap seulement | Refuse la police avec `InvalidFont` |

Les bits d'intégration ne sont pas toute la licence : l'application reste responsable du droit d'utiliser la police.

## Mesurer et couper les lignes

```php
$width = $font->width('12,50 $', 10);                       // in points
$lines = Document::wrap($description, 240, 10, $font);      // non-empty list of lines
```

`wrap()` coupe entre les mots, et coupe entre ses caractères un mot trop long pour une ligne ; les sauts de ligne et
les tabulations comptent comme des espaces, et un texte vide donne une ligne vide.
