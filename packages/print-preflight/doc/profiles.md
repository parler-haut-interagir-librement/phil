# Printer profiles

A `PrintProfile` holds what one printer asks of a file. Every argument is named and has a default that suits most
offset and digital printers; a profile for a given printer sets only what differs.

```php
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Size;

$booklet = new PrintProfile(
    trimSizes: [Size::a5()],
    bleed: Length::mm(3),
    safetyMargin: Length::mm(6),
    minimumPages: 8,
    maximumPages: 64,
    pageMultiple: 4,
);
```

## Rules

| Argument | Default | Rule |
|---|---|---|
| `trimSizes` | `[]` | The trim sizes the printer prints, each orientation it accepts listed on its own; empty for any size |
| `uniformTrimSize` | `true` | Every page has the trim size of the first page |
| `bleed` | `Length::mm(3)` | The bleed each page must carry, and how far past the trim edge an element that bleeds must reach |
| `safetyMargin` | `Length::mm(5)` | How far inside the trim edge content must stay |
| `minimumResolution` | `150` | The effective resolution, in pixels per inch, below which an image is an error |
| `targetResolution` | `300` | The effective resolution below which an image is a warning |
| `maximumResolution` | `null` | The effective resolution above which an image is a notice, as weight the printer discards; none by default |
| `minimumPages` | `1` | The fewest pages the printer binds |
| `maximumPages` | `null` | The most pages it binds; no limit by default |
| `pageMultiple` | `1` | The page count must be a multiple of it: 2 for a sheet printed on both sides, 4 for saddle stitching |
| `colourWorkflow` | `ColourWorkflow::Cmyk` | The colours the printer expects: CMYK, or RGB for services that convert themselves |
| `allowSpotColours` | `false` | Whether the printer runs spot colour plates |
| `maximumInkCoverage` | `300` | The total ink coverage, in percent, above which an image or a flat colour is an error, in a CMYK workflow: 300 suits coated paper on an offset press, 260 to 280 uncoated paper, 240 newsprint |
| `minimumLineWidth` | `Length::pt(0.25)` | The thinnest line the printer reproduces; a thinner one is a warning |
| `allowOverprint` | `true` | Whether the printer honours overprint; when it does not, every element set to overprint is a warning |
| `allowTransparency` | `true` | Whether the printer accepts live transparency, as PDF/X-4 does; when it does not, as with PDF/X-1a, every element that uses it is a warning |
| `severities` | `[]` | The severity of a rule, by its code, in place of its default |
| `ignore` | `[]` | The codes of the rules not to report |

A profile whose rules contradict each other is refused with `InvalidProfile`: a negative bleed or safety margin, a
minimum resolution that is not positive, a target below the minimum, a maximum below the target, a minimum page count
below one, a maximum below the minimum, a page multiple below one, a maximum ink coverage that is not positive, a
negative minimum line width.

## Severities

Each built-in rule has a default severity (see [Checks and findings](checks.md)). A printer that adds blank pages
itself makes the multiple a warning; one that never converts colours makes RGB an error; an application that does not
want notices about colour ignores them:

```php
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;

$profile = new PrintProfile(
    pageMultiple: 4,
    severities: [Rule::PageCountMultiple->value => Severity::Warning, Rule::ColourRgbInCmyk->value => Severity::Error],
    ignore: [Rule::ColourSpot->value],
);
```

The keys are codes, not `Rule` cases, so a profile also sets the severity of the findings of a check the application
adds.
