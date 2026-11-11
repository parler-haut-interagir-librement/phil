<!-- phil-dev source-sha256=7563e2862eb5d000dd498a422068b2cf04ef0bcabcbd63214e9dd49d8beb9fc9 -->
# Matrices

## Raster

Un `Raster` est une image gardée en mémoire en couleurs vraies avec un canal alpha. Il ne change jamais : les
compositeurs dessinent sur des copies.

| Méthode | Ce qu'elle fait |
|---|---|
| `Raster::fromBytes($bytes, $maxPixels)` | Décode des octets PNG, JPEG, WebP, GIF, AVIF ou BMP, selon ce que prend en charge la construction de GD |
| `Raster::fromFile($path, $maxPixels)` | Lit un fichier, puis le décode comme le fait `fromBytes()` |
| `Raster::fromGdImage($image)` | Copie une image GD que détient l'application ; modifier cette image ensuite laisse la matrice intacte |
| `Raster::blank($width, $height, $colour)` | Une image d'une seule couleur, transparente par défaut |
| `width`, `height` | La taille en pixels |
| `pixel($x, $y)` | La `Colour` d'un pixel, son alpha allant de 0, transparent, à 255, opaque |
| `toGdImage()` | Une copie pour les appels à GD propres à l'application |
| `png($dpi)` | Des octets PNG avec le canal alpha, la résolution écrite dans le fichier quand elle est donnée |
| `webp($quality)` | Des octets WebP à une qualité de 0 à 100, 82 par défaut, avec le canal alpha |

Le décodage lit d'abord la taille qu'annonce l'image et refuse, avant de la décoder, une image de plus de pixels que la
limite, 50 mégapixels par défaut (`Raster::DEFAULT_MAX_PIXELS`) : un petit fichier qui annonce une image énorme ne
prend jamais la mémoire. Des octets que GD ne sait pas décoder, une chaîne vide ou un fichier illisible sont refusés
avec `InvalidImage` ; l'avertissement qu'a émis GD, s'il en a émis un, est l'exception précédente de l'exception. Une
image à palette est convertie en couleurs vraies.

GD garde sept bits de transparence : un alpha écrit de 0 à 255 se relit à un pas de 255 ÷ 127 près.
`Raster::gdColour()` donne l'entier qu'écrit GD pour une `Colour`. Un pixel entièrement transparent ne garde aucune
couleur : décodé ou copié, il se lit comme du noir transparent.

## Colour

Une `Colour` est faite de rouge, de vert, de bleu et d'alpha, chacun de 0 à 255, l'alpha valant 255, opaque, par
défaut. `Colour::fromHex('#336699')` et `Colour::fromHex('#336699cc')` lisent l'hexadécimal, le croisillon étant
facultatif, dans l'une ou l'autre casse ; `toHex()` le réécrit, avec l'alpha seulement s'il n'est pas opaque.
