# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The package is one adapter: `WeasyPrintEngine`, the `Phil\PagedMediaBundle\Application\Contract\PrintEngine` of
`phil/paged-media-bundle`. It declares no port of its own.

| To | Do |
|---|---|
| Use it | Register it under the port: see [Wiring the service](installation.md#wiring-the-service) |
| Point to another command | `$binary` of `WeasyPrintEngine`: a name found on the `PATH`, or a path |
| Change how long a print may take | `$timeout` of `WeasyPrintEngine`, in seconds |
| Give documents images and fonts | `$assets` of `WeasyPrintEngine`, a `LocalAssets` on a directory |
| Change the limits on files | `$maxFileBytes` and `$maxTotalBytes` of `LocalAssets` |
| Choose the standard, output intent, tags, forms and images of the PDF | `$pdf` of `WeasyPrintEngine`, a `PdfOptions`: see [PDF output](pdf-output.md) |
| Print two kinds of PDF | Two engine services, each with its `PdfOptions` |
| Check the command at deployment | `WeasyPrintEngine::assertSupported()` |
| Use another engine | Write another `PrintEngine`, or decorate this one as a service: `WeasyPrintEngine` is final |

The arguments that confine the command are fixed, and `PdfOptions` adds only checked values: the confinement is what
makes a document harmless. See
[Security](security.md).
