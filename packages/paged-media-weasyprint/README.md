# phil/paged-media-weasyprint

**HTML and CSS printed as a PDF by WeasyPrint for phil/paged-media-bundle, with the network and the disk out of its reach.**

phil/paged-media-weasyprint is the WeasyPrint engine of `phil/paged-media-bundle`. `WeasyPrintEngine` implements the
bundle's `PrintEngine` port by calling the `weasyprint` command: the document goes in on its standard input, the PDF
comes out on its standard output, and a failure throws `WeasyPrintFailed` with the exit code and the error output.

Reach for it when a document needs what a paged-media engine does — running headers, page counters, footnotes,
columns, CSS Paged Media — and the machine can run WeasyPrint. The bundle's native writer fits a document drawn in
code, and needs no engine.

It is a package of its own so that `phil/paged-media` and `phil/paged-media-bundle` ask for no Python on the machine.

The command may only fetch `data:` addresses: a document cannot reach a file of the server or an address of its
network, whoever wrote it, and a remote image is never fetched. `LocalAssets` gives a document the images, fonts and
colour profiles of one directory, by writing them into it before the command starts. See [Security](doc/security.md).

`PdfOptions` chooses what the PDF is: PDF/A, PDF/UA or PDF/X with the ICC profile of its printing condition, tagged,
with form fields, its images resampled. See [PDF output](doc/pdf-output.md).

## Status

| Part | State |
|---|---|
| `WeasyPrintEngine` | Implemented and tested |
| `LocalAssets`: images, fonts and ICC profiles of one directory | Implemented and tested |
| `PdfOptions` and `PdfVariant`: PDF/A, PDF/UA, PDF/X and its output intent, tags, forms, images | Implemented and tested, and run against WeasyPrint 70 |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the version of WeasyPrint and its detection, the service wiring. |
| [PDF output](doc/pdf-output.md) | The standard of the PDF, its output intent and ICC profile, tags, forms, images. |
| [Security](doc/security.md) | What the command may fetch, the assets directory, the limits. |
| [Exceptions](doc/exceptions.md) | What `WeasyPrintFailed` reports. |
| [Extension points](doc/extension-points.md) | The arguments an application chooses, and what it replaces. |
| [Testing](doc/testing.md) | The fake command of the unit tests, and the test of the real one. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/paged-media-weasyprint
```

One line of `config/services.yaml` makes it the engine of the bundle:

```yaml
services:
    # …
    Phil\PagedMediaBundle\Application\Contract\PrintEngine: { class: Phil\PagedMediaWeasyprint\WeasyPrintEngine }
```

```php
$output = $this->pagedMedia->printHtml(new PrintableHtml($html, [$css], 'fr', 'The book'), 'book.pdf');
```

## Requirements

PHP 8.5 or later with `dom`, `libxml` and `sodium`; `symfony/process` 8.2; `phil/paged-media-bundle` 0.1; the
`weasyprint` command, version 70 or later, on the machine that prints.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
