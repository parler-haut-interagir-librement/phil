# Rasters

## Raster

A `Raster` is an image held in memory in true colour with an alpha channel. It never changes: the compositors draw on
copies.

| Method | What it does |
|---|---|
| `Raster::fromBytes($bytes, $maxPixels)` | Decodes PNG, JPEG, WebP, GIF, AVIF or BMP bytes, as the GD build supports them |
| `Raster::fromFile($path, $maxPixels)` | Reads a file, then decodes it as `fromBytes()` does |
| `Raster::fromGdImage($image)` | Copies a GD image the application holds; changing that image later leaves the raster alone |
| `Raster::blank($width, $height, $colour)` | An image of one colour, transparent by default |
| `width`, `height` | The size in pixels |
| `pixel($x, $y)` | The `Colour` of a pixel, its alpha from 0, transparent, to 255, opaque |
| `toGdImage()` | A copy for GD calls of the application's own |
| `png($dpi)` | PNG bytes with the alpha channel, the resolution written in the file when given |
| `webp($quality)` | WebP bytes at a quality from 0 to 100, 82 by default, with the alpha channel |

Decoding reads the size the image declares first and refuses an image of more pixels than the limit, 50 megapixels by
default (`Raster::DEFAULT_MAX_PIXELS`), before it is decoded: a small file that declares a huge image never takes the
memory. Bytes GD cannot decode, an empty string or a file that cannot be read are refused with `InvalidImage`; the
warning GD raised, when it raised one, is the exception's previous exception. A palette image is turned into true
colour.

GD keeps seven bits of transparency: an alpha written from 0 to 255 is read back to within one step of 255 ÷ 127.
`Raster::gdColour()` gives the integer GD writes for a `Colour`. A fully transparent pixel keeps no colour: decoded or
copied, it reads as transparent black.

## Colour

A `Colour` is red, green, blue and alpha, each from 0 to 255, alpha 255 opaque by default.
`Colour::fromHex('#336699')` and `Colour::fromHex('#336699cc')` read hex, the hash optional, in either case;
`toHex()` writes it back, with the alpha only when it is not opaque.
