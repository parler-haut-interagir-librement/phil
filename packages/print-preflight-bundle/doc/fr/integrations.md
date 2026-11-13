<!-- phil-dev source-sha256=cd9120879629ecb0b2306394f555f8abfef9399865b38d71d77034b26efe3186 -->
# Intégrations

## Le moteur PDF natif

Quand `phil/paged-media` est installé, `PdfLayout` dessine sur un document de son moteur natif et enregistre ce qu'il
place :

```php
$layout = new PdfLayout(new Document());
$bleed = Length::mm(3);
$layout->addPage(PageSize::of(148 * 72 / 25.4 + 2 * $bleed->points, 210 * 72 / 25.4 + 2 * $bleed->points), $bleed);
$layout->image('cover', Image::fromFile($cover), 0, 0, $width, $height, bleeds: true);
$layout->text('title', 40, 520, 'The Lighthouse', 24, $pdfFonts->get('Example Serif', 700));

$report = $preflighter->check($layout->document(), 'booklet');
$pdf = $layout->pdf()->render();
```

| Méthode | Dessine | Enregistre |
|---|---|---|
| `addPage(PageSize $size, ?Length $bleed)` | Une page de la taille de zone média donnée | Une page dont le format rogné est la zone média moins le fond perdu de chaque côté |
| `image(string $id, Image $image, x, y, width, height, bool $bleeds)` | L'image | Une image placée avec sa taille en pixels et l'espace colorimétrique de l'image PDF |
| `rectangle(string $id, x, y, width, height, ?Color $fill, ?Color $stroke, bool $bleeds)` | Le rectangle | Une boîte, en gris ou en RVB selon son remplissage, sinon son contour, sinon noire |
| `text(string $id, x, y, string $text, float $size, $font, ?Color $color)` | La ligne de texte | Une boîte aussi large que la mesure du texte et aussi haute que la taille, à partir d'un quart de la taille sous la ligne de base ; et sa police |

Les coordonnées sont celles du moteur, en points depuis le coin inférieur gauche de la zone média ; `document()` les
transforme en cadres à partir de la zone de coupe avec `Rectangle::fromPdf()`. Une police TrueType est enregistrée
comme intégrée, en sous-ensemble sauf si sa licence l'interdit — le moteur a déjà refusé une police qui interdit
l'intégration —, sous son nom PostScript ; une police standard est enregistrée comme non intégrée, ce que signale le
contrôle préalable. `pdf()` donne le document du moteur, pour en produire le rendu ou dessiner ce qui ne demande
aucune vérification.

## La médiathèque

Quand `phil/media-bundle` est installé, `MediaImages` place l'une de ses images :

```php
$image = $mediaImages->placed($media, Rectangle::mm(-3, -3, 154, 216), bleeds: true);
```

`placed(Media $media, Rectangle $frame, ColourSpace $colourSpace = Rgb, bool $bleeds = false, ?Size $scaledTo = null)`
prend la taille en pixels que la bibliothèque a enregistrée au téléversement et nomme l'image par l'identifiant du
média. Un média qui n'est pas une image de taille connue lève `InvalidMeasurement`.

## Le catalogue de polices

Quand `phil/fonts-bundle` est activé, `CatalogueFontResolver` est le `FontResolver` des documents mesurés : une police
nommée par famille, graisse et style est la fonte de son catalogue, avec les permissions d'intégration de son fichier ;
une fonte qu'il ne contient pas lève son `FontNotFound`. Quand le paquet est installé mais que son bundle n'est pas
activé, `FontCataloguePass` retire le résolveur, sauf si l'application a fait du port un alias vers un résolveur à
elle.
