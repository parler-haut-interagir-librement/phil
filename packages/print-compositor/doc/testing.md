# Testing

## The library's tests

The suite `tests/Unit` draws small images pixel by pixel and compares every pixel of the results: the scaling and
alignment of images in their boxes, the order of layers and items, the blending of translucent pixels, one file per
ink, the exact shading of a mockup on grey columns at several strengths, its caps, a black zone and a transparent
photo. Texts are drawn in a TrueType font the tests write themselves, whose glyphs are rectangles of known sizes, with
side bearings and descenders: the size a text takes, its lines, its ink and its alignment are checked to the pixel at
72 dpi, where a point is a pixel. Its collection and WOFF forms check the fonts refused.

From the monorepo root:

```sh
vendor/bin/phpunit packages/print-compositor/tests
```

The library is held to 100 % covered mutation score by Infection.

## Benchmarks

`PrintCompositorBench` composes a zone of 100 × 100 mm, an image under a fitted text, at 72, 150 and 300 dpi, and fits
the text alone at the same resolutions. From the monorepo root:

```sh
composer bench -- packages/print-compositor/tests/Benchmark
```

In worker mode, `tests/Worker/worker.php` is a page FrankenPHP serves from one process across requests, composing the
same zone at 150 dpi into a PNG file; the compositor and the type face it builds before its loop serve every request.
It needs the GD extension, with FreeType, in the FrankenPHP image:

```sh
composer bench:worker phil/print-compositor
```

## Testing code that composes

Compose small jobs at a low resolution and look at pixels rather than at encoded bytes, which change with the GD and
libpng versions:

```php
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Zone;

$red = Raster::blank(1, 1, Colour::fromHex('#ff0000'));
$job = new PrintJob(new Zone('front', 25.4, 25.4), [new Layer('artwork', [new ImageItem($red, new Box(0, 0, 25.4, 25.4))])], 10);
$file = new PrintCompositor()->compose($job)->raster();

self::assertSame(10, $file->width);
self::assertEquals(Colour::fromHex('#ff0000'), $file->pixel(5, 5));
```

To test the refusal of a customer's text, fit a `TextItem` with `TextFitter` and expect `TextDoesNotFit` or
`MissingGlyphs`: no file is drawn.
