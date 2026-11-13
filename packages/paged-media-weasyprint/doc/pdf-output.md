# PDF output

Without options, the engine writes the PDF WeasyPrint writes by default. `PdfOptions`, the fourth argument of
`WeasyPrintEngine`, chooses its standard, its output intent, its tags, its form fields and its images:

```php
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;

$engine = new WeasyPrintEngine(pdf: new PdfOptions(PdfVariant::PdfA2u));
```

Each option is one argument of the command, checked when the object is built: a value out of range throws
`InvalidArgumentException`, and nothing of the document reaches the command line.

| Argument | Accepted | Command argument |
|---|---|---|
| `$variant` | A `PdfVariant` | `--pdf-variant` |
| `$outputIntent` | `srgb`, `device-cmyk`, or the dashed name of a `@color-profile` of the document, such as `--press` | `--output-intent` |
| `$tags` | `true` to tag the PDF for accessibility | `--pdf-tags` |
| `$forms` | `true` to make the form fields of the document PDF form fields | `--pdf-forms` |
| `$maxImageDpi` | 1 to 2400 dots per inch | `--dpi` |
| `$jpegQuality` | 0 to 95 | `--jpeg-quality` |
| `$optimizeImages` | `true` to make images smaller without losing quality | `--optimize-images` |

## Standards

`PdfVariant` holds the standards WeasyPrint 70 writes, under its names: PDF/A-1a, 1b, 2a, 2b, 2u, 3a, 3b, 3u, 4e, 4f
and 4u, PDF/UA-1 and PDF/UA-2, PDF/X-1a, PDF/X-3, PDF/X-4 and PDF/X-5g. WeasyPrint writes the metadata, the identifier
and, for PDF/A, an sRGB output intent; the document must still hold what the standard asks, such as a title and a
language for PDF/UA, which `PrintableHtml` carries.

The engine does not validate the PDF. To check it, or to repair it, send it through the Gotenberg client of
`phil/paged-media`, whose `validate()` and `Remediate` stage read PDF/A and PDF/UA.

## PDF/X and its colour profile

A PDF/X file names the printing condition it was made for, by the ICC profile of that condition. WeasyPrint reads it
from a `@color-profile` rule of the document, and the output intent names that rule. A PDF/X variant without such a
profile is refused when the options are built, as WeasyPrint would write the file without an output intent.

The profile comes from the assets directory, like an image: `LocalAssets` takes `.icc` and `.icm` files.

```php
use Phil\PagedMedia\Css\PageRule;
use Phil\PagedMedia\Css\PrinterMarks;
use Phil\PagedMedia\Pdf\PageSize;

$engine = new WeasyPrintEngine(
    assets: new LocalAssets('/srv/print-assets'),               // holds press.icc, from the printer
    pdf: new PdfOptions(PdfVariant::PdfX4, '--press'),
);

$pdf = $engine->pdf(new PrintableHtml($body, [
    '@color-profile --press { src: url("press.icc"); components: c, m, y, k }',
    new PageRule(PageSize::ofMillimetres(148, 210), [54], 8.5, PrinterMarks::Crop)->css(),
    $css,
], 'en', 'The book'));
```

The `components` descriptor is required: WeasyPrint 70 stops with an error on an output intent whose profile does not
list its components. `PageRule`, from `phil/paged-media`, writes the trimmed size, the bleed and the marks: WeasyPrint
records them as the trim box and the bleed box of each page.

## Two kinds of PDF

The options belong to the engine, not to a document. An application that prints both archived documents and press
files registers two engines, one under the port and the other under its own name:

```yaml
services:
    Phil\PagedMediaBundle\Application\Contract\PrintEngine: { class: Phil\PagedMediaWeasyprint\WeasyPrintEngine }

    app.press_engine:
        class: Phil\PagedMediaWeasyprint\WeasyPrintEngine
        arguments:
            $assets: '@Phil\PagedMediaWeasyprint\LocalAssets'
            $pdf: !service
                class: Phil\PagedMediaWeasyprint\PdfOptions
                arguments: [!php/enum Phil\PagedMediaWeasyprint\PdfVariant::PdfX4, '--press']
```
