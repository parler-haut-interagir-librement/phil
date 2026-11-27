<!-- phil-dev source-sha256=50bf98713b342216e0323624f68115fb46df998f7a78fd338051add0dddaa495 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/print-compositor` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Colour`, `Box`, `PixelBox`, `Zone`, `Units`, `HorizontalAlign`, `VerticalAlign` et `Fit` : des couleurs sRGB avec
  leur opacité lues de l'hexadécimal, des rectangles en millimètres depuis le coin supérieur gauche d'une zone et en
  pixels, des zones d'impression à leur taille réelle, des millimètres et des points changés en pixels à une résolution,
  des alignements sur les deux axes, des images contenues dans leur boîte ou qui la couvrent.
- `Raster` : une image en couleurs vraies avec son canal alpha, décodée à partir d'octets ou d'un fichier avec une
  limite de pixels vérifiée avant le décodage, copiée d'une image GD, vierge, lue pixel par pixel, encodée en PNG avec
  sa résolution ou en WebP à une qualité.
- `TypeFace` : une police TrueType ou OpenType lue par `phil/fonts`, les WOFF, WOFF2 et collections refusées, et les
  caractères d'un texte qu'elle ne couvre pas.
- `ImageItem`, `TextItem`, `Item`, `Layer` et `PrintJob` : les éléments d'un fichier d'impression dans leurs boîtes, des
  couches dessinées dans l'ordre dans une encre, une tâche à 300 ppp par défaut dont chaque élément se trouve dans sa
  zone.
- `TextFitter`, `FittedText` et `FittedLine` : la plus grande taille d'un texte, par demi-points de son maximum jusqu'à
  son minimum, à laquelle son encre tient dans sa boîte une fois coupée aux espaces ; un texte avec un glyphe manquant
  ou qui ne tient pas à son minimum refusé.
- `PrintCompositor` et `PrintFiles` : un fichier d'impression transparent par encre à la taille réelle de la zone, les
  images mises à l'échelle et alignées, les textes dessinés dans leur couleur et alignés par leur encre, chaque fichier
  comme image matricielle ou comme PNG à la résolution de la tâche.
- `FacePhoto`, `Placement`, `Mockup` et `MockupCompositor` : des œuvres placées dans les zones d'une photo d'un produit
  vierge, assombries et éclaircies par l'ombrage du tissu relatif à la zone, à une force de 0 à 1.
- `PrintCompositorException`, implémentée par `InvalidLayout`, `InvalidImage`, `UnsupportedFont`, `MissingGlyphs`,
  `TextDoesNotFit` et `MissingCapability`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
