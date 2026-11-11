# Mockups

A mockup is the photo of a blank product with artworks placed on it, as a customer sees the product on a site.

## Photos and zones

A `FacePhoto` is the photo of one face of the product in one variant — the front of the shirt in black — with the
rectangle each print zone covers on it, in pixels:

```php
use Phil\PrintCompositor\FacePhoto;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Raster;

$photo = new FacePhoto('front', 'black', Raster::fromFile('/path/to/front-black.jpg'), [
    'front' => new PixelBox(312, 260, 400, 500),
]);
```

Each variant has its photo, so a zone can sit at other pixels on another photo. A zone that goes past its photo is
refused. A `PixelBox` with the proportions of the zone's real size keeps the artwork at its printed proportions.

## Placements

A `Placement` puts an artwork in a zone of the photo; the artwork is contained in the zone's rectangle, its proportions
kept, centred across and at the top by default, as a print is aligned on a garment. The colour print file of the zone
is the usual artwork: it has the zone's proportions and fills the rectangle exactly.

A `Mockup` is the photo, its placements and the shading strength, from 0 to 1, 0.8 by default. A placement in a zone
the photo does not place is refused.

## Shading

`MockupCompositor::compose()` draws each artwork on a copy of the photo. Each pixel of the artwork is multiplied by a
shade read from the photo under it:

```
shade = 1 + strength × (luminance of the photo pixel ÷ mean luminance of the zone − 1)
```

capped at `MockupCompositor::MAX_SHADE`, 2, and each channel at 255, then blended with the photo by the artwork's
opacity. The luminance weighs red, green and blue as ITU-R BT.709 does.

A fold darker than the fabric around it darkens the print, a highlight lightens it, a flat area leaves it as drawn.
The shade is relative to the fabric's own mean, unlike a multiply blend: a print keeps its colours on a black garment
as on a white one, and only the folds show through. A zone of pure black leaves the print as drawn. The photo's own
transparency, for a product cut out from its background, is kept.

The pixels are read and written in PHP: a zone of 400 × 500 pixels is shaded in a fraction of a second. Mockups of
every variant of a product are generated once, when the product is, not on each page view.

## WebP

The result is a `Raster` of the photo's size; `webp()` encodes it for a site, at quality 82 by default:

```php
$mockup = new MockupCompositor()->compose(new Mockup($photo, [new Placement('front', $files->raster())]));
file_put_contents('front-black.webp', $mockup->webp());
```
