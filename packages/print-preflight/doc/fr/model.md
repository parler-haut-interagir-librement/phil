<!-- phil-dev source-sha256=37005d92a924ec41494f20db3ec8648ba134cc629229bf5627c8c8225e89f64a -->
# Le modèle de document

La vérification ne lit jamais de fichier. L'application décrit son document — ce qu'elle a placé sur quelle page, à
quelle taille — et la description est la même, que le document soit un PDF écrit par l'application ou une page HTML
qu'un navigateur met en page pour l'impression.

## Les unités

Chaque longueur est une `Length`, gardée en points PostScript, 72 au pouce, l'unité du PDF :

| Fabrique | Unité |
|---|---|
| `Length::pt(12)` | Points |
| `Length::mm(3)` | Millimètres |
| `Length::in(0.125)` | Pouces |
| `Length::px(96)` | Pixels CSS, 96 au pouce quel que soit l'écran, comme un navigateur met en page une page imprimée |

`Size` et `Rectangle` ont les mêmes quatre fabriques, et `Size` a `a4()`, `a5()` et `letter()`. Deux longueurs ou deux
formats sont comparés à `Length::TOLERANCE` près, un demi-point (environ 0,18 mm) : assez pour absorber un format en
millimètres arrondi à des pixels CSS entiers, ou les décimales d'un PDF, sans cacher une vraie différence.

## Les coordonnées

Un `Rectangle` est le cadre d'un élément : son coin supérieur gauche mesuré depuis le **coin supérieur gauche du format
rogné**, y croissant vers le bas comme en CSS, puis sa largeur et sa hauteur. Ce qui se trouve dans le fond perdu a des
coordonnées négatives ou dépasse le format rogné : sur une page A5 avec 3 mm de fond perdu, un fond à fond perdu
intégral est `Rectangle::mm(-3, -3, 154, 216)`.

`Rectangle::fromPdf()` transforme des coordonnées PDF — origine au coin inférieur gauche de la page, y croissant vers
le haut — en ce cadre, à partir de la hauteur de la page et du fond perdu que la boîte média de la page porte au-delà
du format rogné de chaque côté.

## Les pages et les éléments

| Type | Contient |
|---|---|
| `Document` | Les pages, dans l'ordre, et les polices avec lesquelles le document s'imprime |
| `Page` | Le format rogné, le fond perdu que porte le fichier de chaque côté (aucun par défaut), les images et les boîtes |
| `PlacedImage` | Un identifiant, son cadre visible, sa taille en pixels, son `ColourSpace` (RVB par défaut), s'il déborde, la taille à laquelle l'image entière est mise à l'échelle quand son cadre la recadre, sa couverture d'encre totale la plus haute quand elle est connue, s'il utilise la transparence |
| `Box` | Tout autre élément — texte, forme, dessin vectoriel, fond — : un identifiant, son cadre, son `ColourSpace` quand il est connu, s'il déborde, la couverture d'encre totale de son aplat, s'il est en surimpression, l'épaisseur de son filet le plus fin quand elle est connue, s'il utilise la transparence |
| `UsedFont` | Le nom d'une police, ses `EmbeddingPermissions` de `phil/fonts`, si le fichier l'intègre, s'il en intègre un sous-ensemble |

L'identifiant d'un élément est ce que nomment les constats : un identifiant d'élément, un identifiant de bloc, un nom
de fichier, tout ce qui permet à l'application de le désigner.

Un élément qui **déborde** est censé sortir de la page : partout où il touche le bord de coupe, il doit le dépasser du
fond perdu de l'imprimeur. Tout autre élément est du **contenu**, qui doit rester dans le format rogné et à l'écart de
la marge de sécurité. Un fond, une photographie pleine page ou une bande de couleur déborde ; du texte, un logo, un
numéro de page ne déborde pas.

### La résolution et le recadrage

La résolution effective d'une image est sa taille en pixels divisée par la taille à laquelle elle s'imprime, en
pouces, la plus basse des deux directions. Quand le cadre montre l'image entière, le cadre est cette taille. Quand le
cadre recadre une image plus grande, passez `scaledTo`, la taille à laquelle l'image entière est mise à l'échelle :

```php
new PlacedImage('portrait', Rectangle::mm(20, 30, 50, 50), 1200, 1600, scaledTo: Size::mm(50, 66.7));
```

### L'encre, la surimpression, les filets et la transparence

Ce que l'application sait de la façon dont un élément s'imprime, elle le passe ; ce qu'elle ne sait pas, elle l'omet,
et la vérification qui en a besoin ne signale rien sur cet élément. La vérification ne mesure rien de tout cela :
l'application qui convertit une image en CMJN connaît son pixel le plus dense, et celle qui trace un filet connaît son
épaisseur.

| Argument | Sur | Contient |
|---|---|---|
| `inkCoverage` | `PlacedImage` | La couverture d'encre totale la plus haute parmi ses pixels, en pour cent : la somme des valeurs C, M, J et N du pixel le plus dense, 400 pour un noir plein sur les quatre plaques |
| `inkCoverage` | `Box` | La couverture d'encre totale de son aplat, en pour cent, la même somme ; 0 pour le blanc |
| `overprint` | `Box` | S'il est réglé pour s'imprimer en surimpression sur ce qui se trouve dessous plutôt que de le défoncer |
| `lineWidth` | `Box` | L'épaisseur du filet le plus fin qu'il trace — un contour, un filet, une bordure —, sous forme de `Length` ; `Length::pt(0)` pour le trait le plus fin que trace le périphérique |
| `transparency` | `PlacedImage`, `Box` | S'il est dessiné avec de la transparence : une couche alpha ou un masque progressif, une opacité sous 1, un mode de fusion autre que normal |

```php
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;

$photo = new PlacedImage('photo-1', Rectangle::mm(10, 10, 128, 90), 1600, 1125, ColourSpace::Cmyk, inkCoverage: 318);
$band = new Box('band', Rectangle::mm(-3, 180, 154, 33), ColourSpace::Cmyk, bleeds: true, inkCoverage: 260);
$rule = new Box('rule', Rectangle::mm(10, 105, 128, 0.1), ColourSpace::Cmyk, lineWidth: Length::mm(0.1));
$title = new Box('title', Rectangle::mm(10, 120, 128, 12), ColourSpace::Cmyk, inkCoverage: 100, overprint: true);
```

Une couverture d'encre ou une épaisseur de filet sous zéro est refusée par `InvalidDocument`.

### Les polices

`UsedFont::fromFont()` prend une face lue par `phil/fonts` et la nomme par son nom complet :

```php
use Phil\Fonts\FontFile;
use Phil\PrintPreflight\UsedFont;

$font = UsedFont::fromFont(FontFile::fromFile($path)->face(), embedded: true, subset: true);
```

Une application qui ne connaît que le `fsType` d'une police construit
`new UsedFont($name, new EmbeddingPermissions($fsType))`.

## À partir d'un document `phil/paged-media`

Une application qui écrit son PDF avec `phil/paged-media` connaît chaque position qu'elle passe à `Document::image()`,
`text()` et `rectangle()` : elle les enregistre à mesure qu'elle dessine, en coordonnées PDF, et les transforme en
cadres avec `Rectangle::fromPdf()`. L'`Image` qu'elle place donne la taille en pixels et le nom de l'espace
colorimétrique PDF, que lit `ColourSpace::fromPdf()` :

```php
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;

$pdf->image($image, $x, $y, $width, $height);

$images[] = new PlacedImage(
    'photo-1',
    Rectangle::fromPdf($x, $y, $width, $height, $pageHeight, $bleed),
    $image->width,
    $image->height,
    ColourSpace::fromPdf($image->colorSpace),
);
```

Les deux bibliothèques ne dépendent pas l'une de l'autre : l'application détient les deux et passe des valeurs de
l'une à l'autre. La couleur dont elle remplit un rectangle donne la couverture d'encre de ce rectangle, et l'épaisseur
de trait qu'elle utilise donne son `lineWidth`.

## À partir d'un rendu HTML

Une page mise en page pour l'impression par un navigateur — par une feuille de style d'impression, un polyfill de
médias paginés ou un navigateur sans interface — donne ce dont le modèle a besoin à un script qui s'exécute sur la page
rendue : pour chaque boîte de page, sa taille en pixels CSS ; pour chaque élément, `getBoundingClientRect()` par
rapport à la boîte de page ; pour chaque image, ses `naturalWidth` et `naturalHeight`. L'application envoie ces
nombres au serveur et construit le modèle avec les fabriques `px` :

```php
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;

$trim = Size::px($page['width'], $page['height']);
$image = new PlacedImage($item['id'], Rectangle::px($item['x'], $item['y'], $item['width'], $item['height']), $item['naturalWidth'], $item['naturalHeight']);
```

`object-fit: cover` recadre une image comme le décrit `scaledTo` : la taille mise à l'échelle est la taille naturelle
multipliée par le plus grand des deux rapports du cadre à la taille naturelle. Les polices viennent de la liste propre
à l'application des fichiers de police que charge sa feuille de style, lus avec `phil/fonts` ; un navigateur intègre
les polices web sous forme de sous-ensembles quand il imprime en PDF.
