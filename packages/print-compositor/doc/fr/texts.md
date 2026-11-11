<!-- phil-dev source-sha256=5f7424066c1e03fb92dd33800cce6da94809781ca3ecc7c108e8f927d7bca95a -->
# Textes

## Polices

Une `TypeFace` est un fichier de police dans lequel des textes sont dessinés :

```php
use Phil\PrintCompositor\TypeFace;

$face = TypeFace::fromFile('/path/to/ExampleSerif.ttf');
$face->font->family();                    // read by phil/fonts
$face->missingCharacters('Grüße, €5');    // the characters the font does not cover, each once
```

Le fichier est lu par `phil/fonts`, dont la police garde le `Font` : ses noms, ses permissions d'intégration, sa table
des caractères. FreeType, au travers de GD, dessine à partir d'un fichier TrueType ou OpenType : un WOFF, un WOFF2 ou
une collection est refusé avec `UnsupportedFont`, tout comme un fichier illisible ou qui n'est pas une police. Le chemin
est résolu à la lecture de la police : elle dessine ainsi à partir du même fichier, où que s'exécute le processus.

Un fichier d'impression est une image : la police n'y est pas intégrée. Vérifier si la licence d'une police permet de
la dessiner dans des images vendues revient à l'application, à partir de la licence que lit `phil/fonts`.

## Éléments de texte

Un `TextItem` est un texte, sa police et sa boîte, avec :

| Argument | Par défaut | Sens |
|---|---|---|
| `colour` | noir opaque | La couleur du texte, alpha compris |
| `maxSize`, `minSize` | 72 et 6 | Les tailles, en points, que peut prendre le texte |
| `horizontal`, `vertical` | `Centre`, `Middle` | La place du texte dans sa boîte |
| `wrap` | `true` | Si le texte passe à la ligne aux espaces pour tenir dans la largeur de la boîte |
| `lineSpacing` | 1.2 | La distance entre deux lignes de base, en cadratins |

Le texte passe à la ligne à ses sauts de ligne dans tous les cas. Un texte vide, une taille minimale qui n'est pas
positive, une taille maximale inférieure à la minimale et un interligne qui n'est pas positif sont refusés avec
`InvalidLayout`.

## Ajustement

`TextFitter::fit()` trouve la taille à laquelle un texte est dessiné, à une résolution donnée, 300 ppp par défaut :

1. Un texte qui contient un caractère que sa police ne couvre pas est refusé avec `MissingGlyphs`, qui nomme chaque
   caractère et son point de code : dessiné, il s'imprimerait comme une boîte vide ou pas du tout.
2. Les tailles sont essayées à partir du maximum en descendant, par pas d'un demi-point, puis le minimum lui-même quand
   les pas le manquent. À chaque taille, le texte est coupé aux espaces en lignes qui ne sont pas plus larges que la
   boîte, un mot plus large que la boîte restant seul sur sa ligne.
3. La première taille dont l'encre entre dans la boîte, en largeur et en hauteur, est gardée. L'encre est mesurée sur
   les pixels que dessine FreeType, et non sur sa boîte englobante, qui peut être de quelques pixels plus large ou plus
   étroite que l'encre selon la police : un texte déclaré comme entrant ne dessine jamais hors de sa boîte. La boîte
   englobante sert seulement à éviter de mesurer les tailles nettement trop grandes : plus de
   `TextFitter::ESTIMATE_MARGIN` fois la boîte.
4. Un texte qui n'entre pas, même à sa taille minimale, est refusé avec `TextDoesNotFit`.

```php
use Phil\PrintCompositor\TextFitter;

$fitted = new TextFitter()->fit($item);
$fitted->size;          // 31.5
$fitted->lineTexts();   // ['Here lies a brave', 'adventurer']
```

Le texte d'un client est vérifié de cette façon au moment de sa saisie, avec le même élément que dessinera le fichier
d'impression : le texte refusé l'est avant la commande, et le texte accepté est dessiné tel qu'il a été montré.
`PrintCompositor` appelle de nouveau l'ajusteur au dessin, et obtient la même réponse.

## Alignement

Le texte est placé selon son encre, et non selon l'avance de ses glyphes : un texte aligné à gauche touche le bord
gauche de sa boîte, un texte aligné à droite le bord droit, et un texte aligné en haut a son encre la plus haute sur le
bord supérieur. Chaque ligne est alignée horizontalement pour elle-même ; les lignes sont alignées verticalement comme
un seul bloc, de l'encre la plus haute de toutes les lignes à la plus basse.
