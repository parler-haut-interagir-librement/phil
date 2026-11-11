# Print files

A print file is what a printer receives for one print zone of a product: a transparent PNG at the zone's real size
and at the resolution the printer asks for.

## Zones, boxes and resolutions

A `Zone` is a name and a size in millimetres: `new Zone('front', 280, 350)` is a zone 28 cm wide and 35 cm high.
Each item of a print file is drawn in a `Box`, in millimetres from the top left corner of the zone:
`new Box(40, 270, 200, 60)` starts 4 cm from the left and 27 cm from the top. A box starts inside its zone and has a
positive size; `PrintJob` refuses an item whose box goes past the zone, edges included as inside.

At a resolution, millimetres become pixels with `Units::pixels()`, rounded to the nearest pixel. A box is turned edge
by edge, so two boxes that touch in millimetres touch in pixels too, and keeps at least one pixel either way. The zone
above is 3307 × 4134 pixels at 300 dpi. Resolutions go from 1 to 2400 dpi.

## Layers and inks

A `PrintJob` holds the zone, its layers in order and its resolution, 300 dpi by default. A `Layer` has a name, its
items in order and an ink, `colour` by default:

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

Each ink is one file. The layers of an ink are drawn on its file in the order of the job, and the items of a layer in
their order, the later over the earlier, translucent pixels blended with what is under them. A glow-in-the-dark ink, a
white underbase or a metallic foil is a layer in its own ink: the printer gets it apart from the colours, at the same
size, so the two files line up. Ink names are the application's: the library only groups by them.

## Images

An `ImageItem` draws a `Raster` in its box, its proportions kept:

| `Fit` | The image |
|---|---|
| `Contain`, the default | As large as the box allows, whole; the box may keep transparent bands |
| `Cover` | Covers the whole box; the part that does not fit is cropped |

`HorizontalAlign` (`Left`, `Centre`, `Right`) and `VerticalAlign` (`Top`, `Middle`, `Bottom`) place a contained image
in its box, centred by default, and choose which part of a covering image is kept.

An artwork that fills the zone is an image in a box the size of the zone. Objects drawn at random, each placed at its
position, are as many images in as many boxes: a table of objects is the application's, the library places what it is
given.

A raster is scaled to its box: an artwork of too few pixels for its box at the job's resolution prints blurred.
`phil/print-preflight` reports the effective resolution of an image before the job is sent.

## Composing

```php
use Phil\PrintCompositor\PrintCompositor;

$files = new PrintCompositor()->compose($job);

$files->inks();           // ['colour', 'glow']
$files->png();            // the colour file, as PNG bytes with the job's resolution written in them
$files->png('glow');      // the glow file
$files->raster();         // the colour file as a Raster, to place on a product photo
```

A text that does not fit its box, or that uses a character its font does not cover, stops the composition with
`TextDoesNotFit` or `MissingGlyphs`: check texts with `TextFitter` when they are entered ([Texts](texts.md)).
Asking `PrintFiles` for an ink the job has no layer in throws `InvalidLayout`.

A print file at 300 dpi of a 28 × 35 cm zone is 13.7 megapixels, about 55 MB in memory while it is drawn: compose
print files in a worker with the memory for it, after the order, rather than in a web request.
