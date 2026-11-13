# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP, with `dom`, `libxml` and `sodium` | 8.5 or later | The floor the package declares; `dom` and `libxml` read the document for `LocalAssets`, `sodium` encodes its files |
| `phil/paged-media-bundle` | ^0.1 | The `PrintEngine` port and `PrintableHtml` |
| `symfony/process` | ^8.2 | Runs the command |
| The `weasyprint` command | 70 or later | Lays the document out |

```sh
composer require phil/paged-media-weasyprint
```

Composer does not install WeasyPrint. It comes with the machine: a system package, a `pip` installation in a virtual
environment, or the image of the application. WeasyPrint needs Python and Pango; its own documentation lists them for
each system.

## The WeasyPrint version

The package is written and tested for WeasyPrint 70, the value of `WeasyPrintEngine::MINIMUM_VERSION`.

```php
$engine->version();          // "70.0", from `weasyprint --version`
$engine->assertSupported();  // throws WeasyPrintFailed on an older command
```

`assertSupported()` starts the command, so it belongs in a deployment step or a health probe, not before each print.
A command so old that it does not know `--allowed-protocols` stops with exit code 2 at the first print: the arguments
that confine it are never silently dropped.

## Wiring the service

The package has no bundle. The application registers the engine under the port, as `paged-media-bundle` looks the port
up by its name and leaves `printHtml()` throwing `NotConfigured` when there is none. One line does it, with the
defaults — the `weasyprint` command of the `PATH`, 120 seconds, no file from the disk:

```yaml
# config/services.yaml
services:
    Phil\PagedMediaBundle\Application\Contract\PrintEngine: { class: Phil\PagedMediaWeasyprint\WeasyPrintEngine }
```

With autowiring on, a `LocalAssets` service of the application is given to the engine as well. Each argument can be
set instead:

```yaml
# config/services.yaml
services:
    Phil\PagedMediaWeasyprint\LocalAssets:
        arguments:
            $directory: '%kernel.project_dir%/assets/print'

    Phil\PagedMediaBundle\Application\Contract\PrintEngine:
        class: Phil\PagedMediaWeasyprint\WeasyPrintEngine
        arguments:
            $binary: '%env(WEASYPRINT_BINARY)%'    # the default is `weasyprint`, found on the PATH
            $timeout: 300.0                        # seconds; the default is 120
            $assets: '@Phil\PagedMediaWeasyprint\LocalAssets'
            $pdf: !service
                class: Phil\PagedMediaWeasyprint\PdfOptions
                arguments: [!php/enum Phil\PagedMediaWeasyprint\PdfVariant::PdfA2u]
```

`$assets` is left out when the documents take no file from the disk, and `$pdf` when WeasyPrint's defaults fit; see
[PDF output](pdf-output.md). The choice stays with the application because
installing a package should not decide which engine prints, and a site may have another one, or a different timeout
per environment.

The classes hold no state between two calls, and can stay in memory in a worker.

Without Symfony's container, the engine is a class like any other:

```php
use Phil\PagedMediaWeasyprint\LocalAssets;
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;

$engine = new WeasyPrintEngine('/usr/local/bin/weasyprint', 300.0, new LocalAssets('/srv/print-assets'), new PdfOptions(PdfVariant::PdfA2u));
```
