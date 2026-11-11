<!-- phil-dev source-sha256=175ee3129c838b7945bc6b48899c7ea382bcc58f525f30063cac7151d111d7eb -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port. Ce que l'application choisit passe par des
arguments.

| Point | Comment |
|---|---|
| Les images | Tout `Raster` : décodé à partir de fichiers ou d'octets que l'application garde où elle veut, ou copié d'une image GD qu'elle a dessinée elle-même avec `Raster::fromGdImage()` |
| Les polices | Tout fichier TrueType ou OpenType, lu avec `TypeFace::fromFile()` ; l'application vérifie la licence que lit `phil/fonts` |
| Les encres | Tout nom sur un `Layer` : il en sort un fichier par nom |
| La résolution | Le `dpi` d'un `PrintJob`, et de `TextFitter::fit()` quand un texte est vérifié d'avance |
| L'ombrage | L'intensité d'une `Mockup`, de 0 à 1 |
| L'encodage | `Raster::png()` et `Raster::webp()`, ou `toGdImage()` pour un encodeur propre à l'application |
| La destination des fichiers | `PrintFiles::png()` et `Raster::webp()` renvoient des octets : l'application les écrit dans son stockage, les joint à une commande ou les envoie à un imprimeur |

`Item` n'est implémenté que par `ImageItem` et `TextItem` : `PrintCompositor` refuse un élément d'une autre classe avec
`InvalidLayout`. Tout autre dessin dans un fichier d'impression est fait par l'application sur une image GD, puis placé
sous forme d'`ImageItem`.
