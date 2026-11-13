# phil/print-preflight-bundle

**What a printer would refuse, checked in a Symfony application against the printers it sends to.**

phil/print-preflight-bundle puts `phil/print-preflight` in a Symfony application. The printers' rules — trim sizes,
bleed, safety margin, resolution, page counts, colour workflow, ink coverage, line width, overprint, transparency,
severities — are named profiles of the configuration, written in millimetres, inches, points or CSS pixels. One service,
`Preflighter`, runs the library's checks and the application's own, registered by an attribute. A document laid out in a
browser is measured by a script and sent as JSON, which `MeasuredDocument` reads; a document drawn with the native PDF
writer of `phil/paged-media` is recorded by `PdfLayout` as it is drawn; an image of `phil/media-bundle` is placed with
the pixel size the library recorded; and a font named by family, weight and style is found, with its embedding
permissions, in the catalogue of `phil/fonts-bundle`. A command checks a measured document and fails a build on an
error.

It is for any Symfony application that sends files to print: a book or zine editor that shows the problems page by
page, a print-on-demand storefront, a pipeline that renders to PDF.

It is a bundle built on `symfony/config`, `symfony/dependency-injection`, `symfony/console` and
`symfony/filesystem`. `phil/fonts-bundle`, `phil/paged-media` and `phil/media-bundle` are optional: each integration is
registered when its package is installed.

## Status

| Part | State |
|---|---|
| Configuration: printer profiles by name, lengths and sizes as text, a default profile | Implemented and tested |
| `Preflighter` and `#[AsPreflightCheck]` | Implemented and tested |
| `MeasuredDocument`: a document measured in a browser, read from JSON | Implemented and tested |
| `phil:print-preflight:check`: findings as text or JSON, failing on an error | Implemented and tested |
| `PdfLayout` for `phil/paged-media`, `MediaImages` for `phil/media-bundle`, `CatalogueFontResolver` for `phil/fonts-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle. |
| [Configuration](doc/configuration.md) | Profiles, lengths and sizes, and the services they give. |
| [Checking](doc/checking.md) | `Preflighter`, the checks of the application, and the command. |
| [Measured documents](doc/measured-documents.md) | The JSON a script on the rendered page sends, and `MeasuredDocument`. |
| [Integrations](doc/integrations.md) | `PdfLayout`, `MediaImages` and the fonts of `phil/fonts-bundle`. |
| [Extension points](doc/extension-points.md) | The services and ports an application relies on or replaces. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, beside the library's exceptions. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/print-preflight-bundle
```

```yaml
# config/packages/phil_print_preflight.yaml
phil_print_preflight:
    profiles:
        booklet: { trim_sizes: [a5], bleed: 3mm, minimum_pages: 8, page_multiple: 4 }
        poster: { trim_sizes: [a4, 297x210mm], bleed: 5mm, colour_workflow: rgb }
```

```php
$report = $preflighter->check($measuredDocument->fromJson($request->getContent()), 'booklet');

foreach ($report->forPage(1) as $finding) {
    // show $finding->message beside the page
}
```

```sh
bin/console phil:print-preflight:check var/measured/zine.json --profile=booklet
```

## Requirements

PHP 8.5 or later, Symfony 8.2, `phil/print-preflight` and `phil/fonts`; `phil/fonts-bundle` to name fonts by family;
`phil/paged-media` for `PdfLayout`; `phil/media-bundle` for `MediaImages`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
