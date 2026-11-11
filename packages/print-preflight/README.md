# phil/print-preflight

**What a printer would refuse, before the file reaches it.**

phil/print-preflight checks a document against the rules of a printer and reports what it finds. It catches images
below the resolution their placed size needs, fonts that are not embedded or whose embedding permissions forbid it,
content that crosses the trim edge or sits in the safety margin, backgrounds meant to bleed that stop short of the
bleed edge, pages of a size the printer does not print or with too little bleed, page counts below the minimum, above
the maximum or off the multiple a binding needs, RGB or spot colours in a CMYK workflow, images and flat colours above
the total ink coverage the press takes, white set to overprint, lines too thin to print, and transparency where the
printer wants a flattened file. Each finding is a blocking error, a warning or a notice, with a code, a sentence and
the values it quotes.

It is for any PHP application that sends files to print: a book or zine made in the browser, a card or poster
designer, a print-on-demand storefront, a pipeline that renders HTML and CSS to PDF. The check runs before the file is
written or sent, on a description of what the document places where, so an editor can show the problems page by page
while the document is still open.

It is a plain PHP library that needs `phil/fonts` to read embedding permissions and nothing
else. It does not open PDF files: the application describes its document with a small model — pages with their trim
size and bleed, placed images with their pixel and placed sizes, other boxes, fonts — which it fills from what it
placed with `phil/paged-media`, or from what a browser measured in an HTML render; what it does not know — the ink
coverage of an image, the width of a line — it leaves out, and the check that needs it stays silent. Nothing is
installed with it.

The design is small: a `Document` holds `Page` values, each with its `PlacedImage` and `Box` elements, and the
`UsedFont` list; a `PrintProfile` holds the printer's rules; `Preflight` runs its checks, each a `Check`, and returns a
`Report` of `Finding` values. An application adds its own rules by adding a `Check`, and changes the severity of any
rule, or ignores it, through the profile.

## Status

| Part | State |
|---|---|
| The model: lengths in points, millimetres, inches and CSS pixels; sizes; rectangles from CSS or PDF coordinates | Implemented and tested |
| Page count against a minimum, a maximum and a multiple, with the number of blank pages to add | Implemented and tested |
| Trim sizes against the printer's list and against the first page; bleed against the printer's | Implemented and tested |
| Fonts: not embedded, restricted, bitmap only, subset against a licence that forbids it, read through `phil/fonts` | Implemented and tested |
| Effective resolution of images at their placed or scaled size, against a minimum, a target and a maximum | Implemented and tested |
| Colour spaces against the workflow: RGB in CMYK, CMYK in RGB, spot colours | Implemented and tested |
| Placement: outside the trim box, across the trim edge, in the safety margin, short of the bleed edge | Implemented and tested |
| Total ink coverage of placed images and flat colours against a maximum, in a CMYK workflow | Implemented and tested |
| Overprint: white set to overprint, overprint where the printer ignores it | Implemented and tested |
| Hairlines: lines thinner than the printer's minimum width | Implemented and tested |
| Transparency where the printer does not accept it | Implemented and tested |
| Severities and ignored rules per profile, checks added by the application, a report as arrays | Implemented and tested |
| Reading a PDF file to fill the model | Not planned: the application describes what it placed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [The document model](doc/model.md) | `Document`, `Page`, `PlacedImage`, `Box`, `UsedFont`, units and coordinates, ink, overprint, lines and transparency; filling it from `phil/paged-media` or an HTML render. |
| [Printer profiles](doc/profiles.md) | `PrintProfile`: every rule, its default, severities and ignored rules. |
| [Checks and findings](doc/checks.md) | What each built-in check reports, the codes and their severities, the report, and adding a check. |
| [Exceptions](doc/exceptions.md) | `PrintPreflightException` and its two exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that runs a preflight. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/print-preflight
```

```php
use Phil\Fonts\FontFile;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;

$page = new Page(Size::a5(), Length::mm(3),
    images: [new PlacedImage('cover', Rectangle::mm(-3, -3, 154, 216), 1200, 1700, bleeds: true)],
    boxes: [new Box('title', Rectangle::mm(4, 20, 138, 30))],
);
$font = UsedFont::fromFont(FontFile::fromFile('/path/to/ExampleSans.ttf')->face(), subset: true);

$profile = new PrintProfile(trimSizes: [Size::a5()], pageMultiple: 4);
$report = new Preflight($profile)->check(new Document([$page], [$font]));

foreach ($report->findings as $finding) {
    echo "{$finding->severity->value}: {$finding->message}\n";
}
// error: The document has 1 page, not a multiple of 4; add 3 blank pages or remove 1.
// warning: The image "cover" on page 1 prints at 197 ppi, below the 300 ppi target.
// notice: "cover" on page 1 is in RGB; the printer converts it to CMYK, and bright colours may dull.
// warning: "title" on page 1 is inside the 5 mm safety margin at the left edge.

$report->passes(); // false: one error
```

## Requirements

PHP 8.5 or later and `phil/fonts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
