# The document model

The preflight never reads a file. The application describes its document — what it placed on which page, at which
size — and the description is the same whether the document is a PDF the application writes or an HTML page a browser
lays out for print.

## Units

Every length is a `Length`, kept in PostScript points, 72 to the inch, the unit of PDF:

| Factory | Unit |
|---|---|
| `Length::pt(12)` | Points |
| `Length::mm(3)` | Millimetres |
| `Length::in(0.125)` | Inches |
| `Length::px(96)` | CSS pixels, 96 to the inch whatever the screen, as a browser lays out a printed page |

`Size` and `Rectangle` have the same four factories, `Size` has `a4()`, `a5()` and `letter()`. Two lengths or two sizes
are compared within `Length::TOLERANCE`, half a point (about 0.18 mm): enough to absorb a millimetre size rounded to
whole CSS pixels, or the decimals of a PDF, without hiding a real difference.

## Coordinates

A `Rectangle` is the frame of an element: its top left corner measured from the **top left corner of the trim box**,
y growing downwards as in CSS, then its width and height. Anything in the bleed has negative coordinates or reaches
past the trim size: on an A5 page with 3 mm of bleed, a full-bleed background is `Rectangle::mm(-3, -3, 154, 216)`.

`Rectangle::fromPdf()` turns PDF coordinates — origin at the bottom left corner of the page, y growing upwards — into
this frame, given the page height and the bleed the page's media box carries beyond the trim box on every side.

## Pages and elements

| Type | Holds |
|---|---|
| `Document` | The pages, in order, and the fonts the document prints with |
| `Page` | The trim size, the bleed the file carries on every side (none by default), the images and the boxes |
| `PlacedImage` | An identifier, its visible frame, its size in pixels, its `ColourSpace` (RGB by default), whether it bleeds, the size the whole image is scaled to when its frame crops it, its highest total ink coverage when known, whether it uses transparency |
| `Box` | Any other element — text, shape, vector drawing, background —: an identifier, its frame, its `ColourSpace` when known, whether it bleeds, the total ink coverage of its flat colour, whether it overprints, the width of its thinnest line when known, whether it uses transparency |
| `UsedFont` | A font's name, its `EmbeddingPermissions` from `phil/fonts`, whether the file embeds it, whether it embeds a subset |

The identifier of an element is what the findings name: an element id, a block id, a file name, whatever lets the
application point at it.

An element that **bleeds** is meant to run off the page: wherever it touches the trim edge it must reach past it by
the printer's bleed. Any other element is **content**, which must stay inside the trim box and clear of the safety
margin. A background, a full-page photograph or a coloured band bleeds; text, a logo, a page number does not.

### Resolution and cropping

An image's effective resolution is its pixel size divided by the size it prints at, in inches, the lower of the two
axes. When the frame shows the whole image, the frame is that size. When the frame crops a larger image, pass
`scaledTo`, the size the whole image is scaled to:

```php
new PlacedImage('portrait', Rectangle::mm(20, 30, 50, 50), 1200, 1600, scaledTo: Size::mm(50, 66.7));
```

### Ink, overprint, lines and transparency

What the application knows of how an element prints, it passes; what it does not know, it leaves out, and the check
that needs it reports nothing about that element. The preflight measures none of it: the application that converts an
image to CMYK knows its densest pixel, and the one that draws a rule knows its width.

| Argument | On | Holds |
|---|---|---|
| `inkCoverage` | `PlacedImage` | The highest total ink coverage among its pixels, in percent: the sum of the C, M, Y and K values of the densest pixel, 400 for solid black on all four plates |
| `inkCoverage` | `Box` | The total ink coverage of its flat colour, in percent, the same sum; 0 for white |
| `overprint` | `Box` | Whether it is set to overprint what lies beneath instead of knocking it out |
| `lineWidth` | `Box` | The width of the thinnest line it draws — a stroke, a rule, a border —, as a `Length`; `Length::pt(0)` for the thinnest line the device draws |
| `transparency` | `PlacedImage`, `Box` | Whether it is drawn with transparency: an alpha channel or soft mask, an opacity below 1, a blend mode other than normal |

```php
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;

$photo = new PlacedImage('photo-1', Rectangle::mm(10, 10, 128, 90), 1600, 1125, ColourSpace::Cmyk, inkCoverage: 318);
$band = new Box('band', Rectangle::mm(-3, 180, 154, 33), ColourSpace::Cmyk, bleeds: true, inkCoverage: 260);
$rule = new Box('rule', Rectangle::mm(10, 105, 128, 0.1), ColourSpace::Cmyk, lineWidth: Length::mm(0.1));
$title = new Box('title', Rectangle::mm(10, 120, 128, 12), ColourSpace::Cmyk, inkCoverage: 100, overprint: true);
```

An ink coverage or a line width below zero is refused with `InvalidDocument`.

### Fonts

`UsedFont::fromFont()` takes a face read by `phil/fonts` and names it by its full name:

```php
use Phil\Fonts\FontFile;
use Phil\PrintPreflight\UsedFont;

$font = UsedFont::fromFont(FontFile::fromFile($path)->face(), embedded: true, subset: true);
```

An application that only knows the `fsType` of a font builds `new UsedFont($name, new EmbeddingPermissions($fsType))`.

## From a `phil/paged-media` document

An application that writes its PDF with `phil/paged-media` knows each position it passes to `Document::image()`,
`text()` and `rectangle()`: it records them as it draws, in PDF coordinates, and turns them into frames with
`Rectangle::fromPdf()`. The `Image` it places gives the pixel size and the PDF colour space name, which
`ColourSpace::fromPdf()` reads:

```php
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;

$pdf->image($image, $x, $y, $width, $height);

$images[] = new PlacedImage(
    'photo-1',
    Rectangle::fromPdf($x, $y, $width, $height, $pageHeight, $bleed),
    $image->width,
    $image->height,
    ColourSpace::fromPdf($image->colorSpace),
);
```

The two libraries do not depend on each other: the application holds both and passes values between them. The
colour it fills a rectangle with gives that rectangle's ink coverage, and the line width it strokes with gives its
`lineWidth`.

## From an HTML render

A page laid out for print by a browser — through a print stylesheet, a paged-media polyfill or a headless browser —
gives what the model needs to a script that runs on the rendered page: for each page box, its size in CSS pixels; for
each element, `getBoundingClientRect()` relative to the page box; for each image, its `naturalWidth` and
`naturalHeight`. The application sends these numbers to the server and builds the model with the `px` factories:

```php
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;

$trim = Size::px($page['width'], $page['height']);
$image = new PlacedImage($item['id'], Rectangle::px($item['x'], $item['y'], $item['width'], $item['height']), $item['naturalWidth'], $item['naturalHeight']);
```

`object-fit: cover` crops an image the way `scaledTo` describes: the scaled size is the natural size times the larger
of the two ratios of the frame to the natural size. Fonts come from the application's own list of the font files its
stylesheet loads, read with `phil/fonts`; a browser embeds web fonts as subsets when it prints to PDF.
