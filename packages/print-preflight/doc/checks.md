# Checks and findings

`Preflight::check()` runs each check on the document, drops the findings the profile ignores, gives the others the
severity the profile sets, and returns a `Report`.

## Severities

| Severity | Meaning |
|---|---|
| `Severity::Error` | The printer would refuse the file, or print it wrong: it must be fixed first |
| `Severity::Warning` | The file prints, but probably not as meant: someone should look |
| `Severity::Notice` | Worth knowing, nothing to fix: a conversion the printer will make |

## Built-in checks and their rules

The checks run in this order, which is also the order of their findings within a page.

| Check | Code (`Rule`) | Default | Found when |
|---|---|---|---|
| `PageCount` | `page-count.empty` | error | The document has no page; the other page count rules are then not checked |
| | `page-count.below-minimum` | error | Fewer pages than `minimumPages` |
| | `page-count.above-maximum` | error | More pages than `maximumPages` |
| | `page-count.multiple` | error | A count that is not a multiple of `pageMultiple`; the finding says how many blank pages to add |
| `PageGeometry` | `page-size.not-accepted` | error | A page whose trim size is not one of `trimSizes` |
| | `page-size.mixed` | error | A page whose trim size differs from the first page's, when `uniformTrimSize` holds and the size is accepted |
| | `bleed.too-small` | error | A page that carries less bleed than `bleed` |
| `Fonts` | `font.not-embedded` | error | A font the file does not embed; its permissions are then not checked |
| | `font.restricted` | error | A font whose embedding usage is restricted |
| | `font.bitmap-only` | error | A font that may only be embedded as bitmaps |
| | `font.subset-forbidden` | error | A font embedded as a subset whose permissions forbid subsetting |
| `ImageResolution` | `image.below-minimum` | error | An image below `minimumResolution` |
| | `image.below-target` | warning | An image below `targetResolution` |
| | `image.above-maximum` | notice | An image above `maximumResolution`, when one is set |
| `Colours` | `colour.rgb-in-cmyk` | notice | An RGB image or box in a CMYK workflow |
| | `colour.cmyk-in-rgb` | notice | A CMYK image or box in an RGB workflow |
| | `colour.spot` | warning | A spot colour when the printer does not run them |
| `InkCoverage` | `ink.image-above-maximum` | error | An image whose `inkCoverage` is above `maximumInkCoverage`, in a CMYK workflow |
| | `ink.flat-above-maximum` | error | A box whose `inkCoverage` is above `maximumInkCoverage`, in a CMYK workflow |
| `Overprint` | `overprint.white` | error | A box set to overprint whose `inkCoverage` is below 0.5 %, when `allowOverprint` holds: it lays no ink and vanishes |
| | `overprint.not-allowed` | warning | A box set to overprint when `allowOverprint` does not hold: the printer knocks it out instead |
| `Transparency` | `transparency.not-allowed` | warning | An image or box that uses transparency when `allowTransparency` does not hold |
| `Hairlines` | `line.hairline` | warning | A box whose `lineWidth` is below `minimumLineWidth` |
| `Placement` | `placement.outside-trim` | warning | An element entirely outside the trim box, which never shows |
| | `placement.crosses-trim` | error | Content that crosses the trim edge |
| | `placement.in-safety-margin` | warning | Content inside the safety margin |
| | `placement.short-of-bleed` | error | An element that bleeds and touches the trim edge without reaching past it by `bleed` |

Edges are compared within the half-point tolerance of `Length`: an element that ends within half a point of the trim
edge touches it, and one that passes it by no more than half a point does not cross it. Line widths are compared
exactly: half a point is wider than most minimums.

An element whose model leaves out its ink coverage or its line width is not checked for it. In an RGB workflow the ink
coverage is not checked: the printer separates the colours and limits the ink itself.

## Findings

A `Finding` holds:

| Property | Content |
|---|---|
| `severity` | Its `Severity`, after the profile |
| `code` | The rule's code, such as `image.below-target` |
| `message` | A sentence in English that names the element, the page and the values: `The image "cover" on page 2 prints at 149 ppi, below the 150 ppi minimum.` |
| `page` | The page it is on, numbered from 1; `null` for the document as a whole |
| `subject` | The identifier of the element or the name of the font it is about, when there is one |
| `parameters` | The values the sentence quotes, by name, for an application that writes its own messages |

The parameters of each code:

| Code | Parameters |
|---|---|
| `page-count.below-minimum` | `count`, `minimum` |
| `page-count.above-maximum` | `count`, `maximum` |
| `page-count.multiple` | `count`, `multiple`, `missing` |
| `page-size.not-accepted` | `size` |
| `page-size.mixed` | `size`, `first` |
| `bleed.too-small` | `bleed`, `required` |
| `image.below-minimum` | `resolution`, `minimum` |
| `image.below-target` | `resolution`, `target` |
| `image.above-maximum` | `resolution`, `maximum` |
| `placement.crosses-trim` | `edges` |
| `placement.in-safety-margin` | `edges`, `margin` |
| `placement.short-of-bleed` | `edges`, `bleed` |
| `ink.image-above-maximum` | `coverage`, `maximum` |
| `ink.flat-above-maximum` | `coverage`, `maximum` |
| `line.hairline` | `width`, `minimum` |

Sizes and lengths are written in millimetres to one decimal (`3 mm`, `148 × 210 mm`), line widths in points to two
decimals (`0.25 pt`), resolutions in whole pixels per inch rounded down, so that 299.6 ppi never reads as the 300 ppi
target, and ink coverages in whole percent rounded up, so that 300.2 % never reads as the 300 % maximum. Edges are
listed top, right, bottom, left.

## The report

`Report` lists the findings about the whole document first, then page by page, each page in the order of the checks.

| Method | Returns |
|---|---|
| `passes()` | Whether no finding is an error: the file may go to the printer |
| `isClean()` | Whether there is no error and no warning |
| `errors()`, `warnings()`, `notices()` | The findings of that severity |
| `forPage(?int $page)` | The findings of one page, or those about the whole document with `null` |
| `count()` | The number of findings |
| `toArray()` | Every finding as an array, ready for JSON |

## Adding a check

A check implements `Check` and returns its findings at their own severity; the profile's severities and ignored codes
apply to them as to the built-in ones:

```php
use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Severity;

final readonly class SpineWidth implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        return \count($document->pages) > 48
            ? [new Finding(Severity::Warning, 'app.spine', 'The spine is wide enough for a title; add one.')]
            : [];
    }
}

$preflight = new Preflight($profile)->withCheck(new SpineWidth());
```

`new Preflight($profile, [...])` replaces the built-in checks with the list given; `Preflight::defaultChecks()`
returns them, to keep some.
