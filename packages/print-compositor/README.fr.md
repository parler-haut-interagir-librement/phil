<!-- phil-dev source-sha256=a0b4182123503b9525cc3d425b746a1a8c5b37b9de56534e06cf446215c6a6af -->
# phil/print-compositor

**Les maquettes que montre une boutique et les fichiers d'impression que reçoit un imprimeur, dessinés à partir des
mêmes calques.**

phil/print-compositor compose les images de produits imprimés. Un **fichier d'impression** est un PNG transparent à la
taille réelle d'une zone d'impression et à la résolution que demande l'imprimeur, 300 ppp par défaut, avec cette
résolution écrite dans le fichier. Il est dessiné à partir de calques d'éléments — des images mises à l'échelle dans
leur boîte, des textes dans une police TrueType ou OpenType ajustés à leur boîte ou refusés — et chaque encre a son
propre fichier : un calque phosphorescent ou une sous-couche blanche part ainsi chez l'imprimeur séparément des
couleurs. Une **maquette** place des visuels, comme le fichier d'impression couleur lui-même, dans les zones d'une
photo du produit vierge, une photo par face et par variante. Les visuels sont assombris et éclaircis par les plis et
les ombres du tissu sous eux, et le résultat est encodé en WebP pour un site.

Elle s'adresse à toute application PHP qui vend des articles imprimés sur commande : une boutique qui génère les photos
de chaque couleur d'un t-shirt à partir d'un seul visuel, un produit qu'un client personnalise avec un court texte dans
une police et une zone imposées, un lot dont les pièces sont tirées au hasard et placées à des positions fixes, une
étape de production qui envoie un fichier par encre à un imprimeur local.

C'est une simple bibliothèque PHP, écrite de zéro sur l'extension GD de PHP, dont elle a besoin avec la prise en charge
de FreeType, de PNG et de WebP, et sur `phil/fonts`, qui lit les polices dans lesquelles ses textes sont dessinés. Rien
n'est installé avec elle, et rien n'est téléchargé. L'[Installation](doc/fr/installation.md) explique pourquoi GD
plutôt qu'Imagick.

La conception est petite : un `PrintJob` contient une `Zone`, une résolution et des `Layer` faits d'éléments
`ImageItem` et `TextItem`. `PrintCompositor` le dessine en `PrintFiles`, un `Raster` par encre ; `TextFitter` trouve la
taille d'un texte, ou le refuse, avant toute commande. Une `Mockup` contient une `FacePhoto` avec la `PixelBox` de
chaque zone et le `Placement` de chaque visuel ; `MockupCompositor` la dessine en `Raster`, encodé avec `webp()`.

## État

| Partie | État |
|---|---|
| Matrices : décodage avec une limite de pixels, images vierges, pixels, PNG avec sa résolution, WebP avec sa qualité | Implémenté et testé |
| Fichiers d'impression : zones en millimètres, boîtes, résolutions, calques dans l'ordre, un fichier par encre | Implémenté et testé |
| Images dans une boîte : contenues ou couvrantes, alignées sur les deux axes, translucidité fondue | Implémenté et testé |
| Textes : polices par `phil/fonts`, glyphes manquants refusés, plus grande taille qui entre par demi-points, retour à la ligne, alignement sur l'encre | Implémenté et testé |
| Maquettes : visuels dans les zones d'une photo de face, ombrage du tissu relatif à la zone, WebP | Implémenté et testé |
| Gestion des couleurs : profils ICC, sortie CMJN | Non prévu : les imprimantes à transfert et DTG acceptent des fichiers PNG sRGB |
| Perspective et déplacement d'un visuel le long des plis | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, et pourquoi GD. |
| [Fichiers d'impression](doc/fr/print-files.md) | `Zone`, `Box`, `PrintJob`, `Layer`, `ImageItem`, encres, `PrintCompositor` et `PrintFiles`. |
| [Textes](doc/fr/texts.md) | `TypeFace`, `TextItem`, `TextFitter` : polices, glyphes manquants, tailles, retour à la ligne et alignement. |
| [Maquettes](doc/fr/mockups.md) | `FacePhoto`, `Placement`, `Mockup`, `MockupCompositor` : zones sur une photo, ombrage, WebP. |
| [Matrices](doc/fr/rasters.md) | `Raster` et `Colour` : décodage, limites, pixels et encodage. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que choisit l'application, et où s'insèrent ses propres appels à GD. |
| [Exceptions](doc/fr/exceptions.md) | `PrintCompositorException` et ses six exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui compose. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/print-compositor
```

```php
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\FacePhoto;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\Mockup;
use Phil\PrintCompositor\MockupCompositor;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Placement;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\TypeFace;
use Phil\PrintCompositor\Zone;

$front = new Zone('front', 280, 350); // millimetres
$job = new PrintJob($front, [
    new Layer('artwork', [new ImageItem(Raster::fromFile('/path/to/artwork.png'), new Box(0, 0, 280, 260))]),
    new Layer('text', [new TextItem('Rest in pieces', TypeFace::fromFile('/path/to/ExampleSerif.ttf'),
        new Box(40, 270, 200, 60), Colour::fromHex('#1a1a1a'), maxSize: 48, minSize: 14)]),
    new Layer('glow', [new ImageItem(Raster::fromFile('/path/to/glow.png'), new Box(0, 0, 280, 260))], ink: 'glow'),
]);

$files = new PrintCompositor()->compose($job);   // 300 dpi by default
file_put_contents('front-colour.png', $files->png());       // 3307 × 4134 pixels, transparent
file_put_contents('front-glow.png', $files->png('glow'));   // the glow ink alone

$photo = new FacePhoto('front', 'black', Raster::fromFile('/path/to/shirt-front-black.jpg'),
    ['front' => new PixelBox(312, 260, 400, 500)]);
$mockup = new MockupCompositor()->compose(new Mockup($photo, [new Placement('front', $files->raster())]));
file_put_contents('shirt-front-black.webp', $mockup->webp());
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-gd` construit avec la prise en charge de FreeType, de PNG et de WebP, `ext-mbstring`,
et `phil/fonts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
