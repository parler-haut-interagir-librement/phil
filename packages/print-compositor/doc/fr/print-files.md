<!-- phil-dev source-sha256=18bb10f8f55d13e923d6325e16f7e9f45d776db8dc495ae7a277cad2e618d1e1 -->
# Fichiers d'impression

Un fichier d'impression est ce que reçoit un imprimeur pour une zone d'impression d'un produit : un PNG transparent à
la taille réelle de la zone et à la résolution que demande l'imprimeur.

## Zones, boîtes et résolutions

Une `Zone` est un nom et une taille en millimètres : `new Zone('front', 280, 350)` est une zone de 28 cm de large et
de 35 cm de haut. Chaque élément d'un fichier d'impression est dessiné dans une `Box`, en millimètres depuis le coin
supérieur gauche de la zone : `new Box(40, 270, 200, 60)` commence à 4 cm de la gauche et à 27 cm du haut. Une boîte
commence à l'intérieur de sa zone et a une taille positive ; `PrintJob` refuse un élément dont la boîte dépasse la
zone, les bords comptant comme l'intérieur.

À une résolution donnée, les millimètres deviennent des pixels par `Units::pixels()`, arrondis au pixel le plus proche.
Une boîte est convertie bord par bord : deux boîtes qui se touchent en millimètres se touchent donc aussi en pixels, et
chacune garde au moins un pixel dans chaque sens. La zone ci-dessus fait 3307 × 4134 pixels à 300 ppp. Les
résolutions vont de 1 à 2400 ppp.

## Calques et encres

Un `PrintJob` contient la zone, ses calques dans l'ordre et sa résolution, 300 ppp par défaut. Un `Layer` a un nom, ses
éléments dans l'ordre et une encre, `colour` par défaut :

```php
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintJob;

$job = new PrintJob($zone, [
    new Layer('artwork', [$artwork]),
    new Layer('dice', [$first, $second, $third]),
    new Layer('glow', [$glowArtwork], ink: 'glow'),
    new Layer('text', [$name]),
], dpi: 300);

$job->inks(); // ['colour', 'glow']
```

Chaque encre est un fichier. Les calques d'une encre sont dessinés sur son fichier dans l'ordre de la tâche, et les
éléments d'un calque dans leur ordre, les derniers par-dessus les premiers, les pixels translucides fondus avec ce qui
se trouve sous eux. Une encre phosphorescente, une sous-couche blanche ou une feuille métallique est un calque dans sa
propre encre : l'imprimeur la reçoit séparément des couleurs, à la même taille, et les deux fichiers se superposent
exactement. Les noms d'encre appartiennent à l'application : la bibliothèque ne fait que regrouper par nom.

## Images

Un `ImageItem` dessine un `Raster` dans sa boîte, ses proportions gardées :

| `Fit` | L'image |
|---|---|
| `Contain`, par défaut | Aussi grande que le permet la boîte, entière ; la boîte peut garder des bandes transparentes |
| `Cover` | Couvre toute la boîte ; la partie qui n'entre pas est recadrée |

`HorizontalAlign` (`Left`, `Centre`, `Right`) et `VerticalAlign` (`Top`, `Middle`, `Bottom`) placent une image
contenue dans sa boîte, centrée par défaut, et choisissent la partie gardée d'une image couvrante.

Un visuel qui remplit la zone est une image dans une boîte de la taille de la zone. Des objets tirés au hasard, chacun
placé à sa position, sont autant d'images dans autant de boîtes : la table des objets appartient à l'application, la
bibliothèque place ce qu'on lui donne.

Une matrice est mise à l'échelle de sa boîte : un visuel qui a trop peu de pixels pour sa boîte à la résolution de la
tâche s'imprime flou. `phil/print-preflight` signale la résolution effective d'une image avant l'envoi de la tâche.

## Composition

```php
use Phil\PrintCompositor\PrintCompositor;

$files = new PrintCompositor()->compose($job);

$files->inks();           // ['colour', 'glow']
$files->png();            // the colour file, as PNG bytes with the job's resolution written in them
$files->png('glow');      // the glow file
$files->raster();         // the colour file as a Raster, to place on a product photo
```

Un texte qui n'entre pas dans sa boîte, ou qui utilise un caractère que sa police ne couvre pas, arrête la composition
avec `TextDoesNotFit` ou `MissingGlyphs` : vérifiez les textes avec `TextFitter` au moment de leur saisie
([Textes](texts.md)). Demander à `PrintFiles` une encre pour laquelle la tâche n'a aucun calque lève `InvalidLayout`.

Un fichier d'impression à 300 ppp d'une zone de 28 × 35 cm fait 13,7 mégapixels, environ 55 Mo en mémoire pendant son
dessin : composez les fichiers d'impression dans un *worker* qui dispose de cette mémoire, après la commande, plutôt
que dans une requête Web.
