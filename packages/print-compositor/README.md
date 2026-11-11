# phil/print-compositor

**The mockups a shop shows and the print files a printer receives, drawn from the same layers.**

phil/print-compositor composes images for printed products. A **print file** is a transparent PNG at the real size of
a print zone and at the resolution the printer asks for, 300 dpi by default, with that resolution written in the
file; it is drawn from layers of items — images scaled into their box, texts in a TrueType or OpenType font fitted to
their box or refused — and each ink gets its own file, so a glow-in-the-dark or a white underbase layer goes to the
printer apart from the colours. A **mockup** places artworks, such as the colour print file itself, in the zones of a
photo of the blank product, one photo per face and variant, darkened and lightened by the folds and shadows of the
fabric under them, and encodes the result in WebP for a site.

It is for any PHP application that sells printed goods made to order: a shop that generates the photos of every
colour of a shirt from one artwork, a product a customer personalises with a short text in an imposed font and zone,
a pack whose pieces are drawn at random and placed at fixed positions, a production step that sends one file per ink
to a local printer.

It is a plain PHP library, written from scratch on PHP's GD extension, which it needs with FreeType, PNG and WebP
support, and on `phil/fonts`, which reads the fonts its texts are drawn in. Nothing is installed with it, and nothing
is downloaded. [Installation](doc/installation.md) explains why GD rather than Imagick.

The design is small: a `PrintJob` holds a `Zone`, a resolution and `Layer` values of `ImageItem` and `TextItem`
items; `PrintCompositor` draws it into `PrintFiles`, one `Raster` per ink; `TextFitter` finds the size of a text, or
refuses it, before anything is ordered. A `Mockup` holds a `FacePhoto` with the `PixelBox` of each zone and the
`Placement` of each artwork; `MockupCompositor` draws it into a `Raster`, encoded with `webp()`.

## Status

| Part | State |
|---|---|
| Rasters: decoding with a pixel limit, blank images, pixels, PNG with its resolution, WebP with its quality | Implemented and tested |
| Print files: zones in millimetres, boxes, resolutions, layers in order, one file per ink | Implemented and tested |
| Images in a box: contained or covering, aligned on both axes, translucency blended | Implemented and tested |
| Texts: fonts through `phil/fonts`, missing glyphs refused, largest size that fits by half points, wrapping, alignment by the ink | Implemented and tested |
| Mockups: artworks in the zones of a face photo, fabric shading relative to the zone, WebP | Implemented and tested |
| Colour management: ICC profiles, CMYK output | Not planned: transfer and DTG printers take sRGB PNG files |
| Perspective and displacement of an artwork along the folds | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and why GD. |
| [Print files](doc/print-files.md) | `Zone`, `Box`, `PrintJob`, `Layer`, `ImageItem`, inks, `PrintCompositor` and `PrintFiles`. |
| [Texts](doc/texts.md) | `TypeFace`, `TextItem`, `TextFitter`: fonts, missing glyphs, sizes, wrapping and alignment. |
| [Mockups](doc/mockups.md) | `FacePhoto`, `Placement`, `Mockup`, `MockupCompositor`: zones on a photo, the shading, WebP. |
| [Rasters](doc/rasters.md) | `Raster` and `Colour`: decoding, limits, pixels and encoding. |
| [Extension points](doc/extension-points.md) | What the application chooses, and where its own GD calls fit. |
| [Exceptions](doc/exceptions.md) | `PrintCompositorException` and its six exceptions. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that composes. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

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

## Requirements

PHP 8.5 or later with `ext-gd` built with FreeType, PNG and WebP support, `ext-mbstring`, and `phil/fonts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
