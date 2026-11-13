# Integrations

## The native PDF writer

When `phil/paged-media` is installed, `PdfLayout` draws on a document of its native writer and records what it places:

```php
$layout = new PdfLayout(new Document());
$bleed = Length::mm(3);
$layout->addPage(PageSize::of(148 * 72 / 25.4 + 2 * $bleed->points, 210 * 72 / 25.4 + 2 * $bleed->points), $bleed);
$layout->image('cover', Image::fromFile($cover), 0, 0, $width, $height, bleeds: true);
$layout->text('title', 40, 520, 'The Lighthouse', 24, $pdfFonts->get('Example Serif', 700));

$report = $preflighter->check($layout->document(), 'booklet');
$pdf = $layout->pdf()->render();
```

| Method | Draws | Records |
|---|---|---|
| `addPage(PageSize $size, ?Length $bleed)` | A page of the media box size given | A page whose trim size is the media box less the bleed on every side |
| `image(string $id, Image $image, x, y, width, height, bool $bleeds)` | The image | A placed image with its pixel size and the colour space of the PDF image |
| `rectangle(string $id, x, y, width, height, ?Color $fill, ?Color $stroke, bool $bleeds)` | The rectangle | A box, grey or RGB after its fill, else its stroke, else black |
| `text(string $id, x, y, string $text, float $size, $font, ?Color $color)` | The line of text | A box as wide as the text measures and as tall as the size, from a quarter of the size below the baseline; and its font |

Coordinates are the writer's, points from the bottom left corner of the media box; `document()` turns them into frames
from the trim box with `Rectangle::fromPdf()`. A TrueType font is recorded as embedded, as a subset unless its licence
forbids subsetting — the writer has already refused a font that forbids embedding —, under its PostScript name; a
standard font as not embedded, which the preflight reports. `pdf()` gives the writer's document, to render it or draw
what needs no check.

## The media library

When `phil/media-bundle` is installed, `MediaImages` places one of its images:

```php
$image = $mediaImages->placed($media, Rectangle::mm(-3, -3, 154, 216), bleeds: true);
```

`placed(Media $media, Rectangle $frame, ColourSpace $colourSpace = Rgb, bool $bleeds = false, ?Size $scaledTo = null)`
takes the pixel size the library recorded at upload and names the image by the medium's identifier. A medium that is
not an image of known size throws `InvalidMeasurement`.

## The font catalogue

When `phil/fonts-bundle` is enabled, `CatalogueFontResolver` is the `FontResolver` of measured documents: a font named
by family, weight and style is the face of its catalogue, with the embedding permissions of its file; a face it does not
hold throws its `FontNotFound`. When the package is installed but its bundle is not enabled, `FontCataloguePass` removes
the resolver, unless the application aliased the port to a resolver of its own.
