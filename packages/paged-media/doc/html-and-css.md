# HTML and CSS engines

The library does not lay out HTML or CSS. A paged-media engine does: it reads a document and its stylesheets, and
breaks them into pages with running headers, page counters, footnotes and columns. `phil/paged-media-bundle` declares
the port such an engine implements, `PrintEngine`, and `phil/paged-media-weasyprint` implements it with WeasyPrint.

What the library gives such an engine is the page the document is printed on.

## The page rule

`Phil\PagedMedia\Css\PageRule` writes the `@page` rule of CSS Paged Media: the trimmed size of the page, its margins,
the bleed and the printer's marks.

```php
use Phil\PagedMedia\Css\PageRule;
use Phil\PagedMedia\Css\PrinterMarks;
use Phil\PagedMedia\Pdf\PageSize;

$rule = new PageRule(
    PageSize::ofMillimetres(148, 210),   // the page once trimmed
    [54, 45],                            // margins, as the CSS shorthand writes them
    8.5,                                 // bleed: 3 mm
    PrinterMarks::Crop,
);

$rule->css();   // "@page { size: 419.53pt 595.28pt; margin: 54pt 45pt; bleed: 8.5pt; marks: crop }"
```

Every measure is in points, as for the native writer, so a `PageSize` means the same page in both. The rule is a
stylesheet of its own, placed before the document's so that a named page or a `:first` page of the document can
change it.

| Argument | Accepted | Written |
|---|---|---|
| `$size` | A `PageSize` | `size`, always |
| `$margins` | A list of one to four margins, each at least 0 and under half the shorter side | `margin`, when there is one; the engine's default otherwise |
| `$bleed` | From 0 to `PageRule::MAXIMUM_BLEED`, one inch | `bleed`, when above 0 |
| `$marks` | `PrinterMarks::None`, `Crop`, `Cross` or `CropAndCross` | `marks`, unless `None` |

A value out of range throws `InvalidDocument`. The same rule gives the same text, so a stylesheet can be cached or
compared.

The engine draws the bleed and the marks outside the trimmed page and records both in the PDF: the media box takes
the bleed, the trim box keeps the page. CSS gives a page with crop marks and no bleed a bleed of 6 points, so the
marks have room.

## What an engine adds

The output intent, PDF/X and its ICC profile, PDF/A and PDF/UA, tags, the confinement of the engine and its limits
belong to the engine's adapter: see `phil/paged-media-weasyprint`. A PDF an engine writes then goes through the
Gotenberg client like any other, to be merged, stamped with a watermark (`StampText`, `StampPdf`), signed or
timestamped.

## Not covered

| Need | State |
|---|---|
| Other engines behind `PrintEngine`, such as Prince or WeasyPrint run as an HTTP service | Not started; each would be a package of its own |
| A limit on the pages an engine prints | Not started; the engine's timeout bounds a costly document |
| Formulas drawn as SVG for an engine without MathML | Not started: WeasyPrint reads no MathML and prints the fallback text of a formula |
