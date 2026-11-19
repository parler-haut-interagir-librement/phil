# Extension points

What an application implements, replaces or relies on.

## Services

| Service | How an application extends it |
|---|---|
| `Phil\DocumentImport\MediaStore` | Aliased to a service of the application's own to keep the images elsewhere: see [Media](media.md) |
| `phil_document_import.media_address` | Replaced by a closure of the application's own, from a medium to the address an image block shows |
| `Phil\DocumentImport\Contract\MediaSource` | Implemented by the application and named in `export.media_source`, for the exporter to read the bytes behind each image address |
| `Phil\SvgSanitizer\SvgSanitizer` | Asked for wherever the application sanitizes SVG with the same policy |
| `Phil\DocumentImportBundle\Application\DocumentImports` | Asked for by the controllers and handlers that import and export the accepted formats |
| `Phil\DocumentImport\DocumentImporter`, `Phil\DocumentImport\DocumentExporter` | Asked for by code that needs every format of the library |
| `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` | Implemented by the application, and the interface aliased to its service with `scan: off`, for a check of its own before each file is parsed: see [Importing](importing.md) |
| `Phil\DocumentImportBundle\Application\Contract\PendingImports` | Implemented by the application and named in `async.store`, to keep the files waiting for the worker elsewhere: see [Imports on a worker](worker.md) |
| `Phil\DocumentImportBundle\Application\DocumentImportQueue` | Asked for by the controllers that import on a worker |

## Events

| Event | When |
|---|---|
| `Phil\DocumentImportBundle\Domain\Event\DocumentImported` | After every import of `DocumentImports`, when the container has an `event_dispatcher` service; `$claim` set for an import on a worker |
| `Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed` | After a file imported on a worker is refused by the library or the screening |

## Messages

| Message | Carries |
|---|---|
| `Phil\DocumentImportBundle\Application\Message\ImportDocument` | `$claim`, the claim of the file in `PendingImports`, and nothing else |

## Templates

| Template | Variables |
|---|---|
| `@PhilDocumentImport/report.html.twig` | `report`, required; `title`, `level` and `id`, optional; overridden in `templates/bundles/PhilDocumentImportBundle/report.html.twig` |

## Configuration

| Option | What it extends |
|---|---|
| `media.*` | Where the images go |
| `formats.*` | Which formats are read and written |
| `limits.*` | How much a document may hold, how deep it may nest, how many objects a PDF may declare |
| `styles` | The block each paragraph style becomes |
| `report.title` | The title of the report block and fragment |
| `scan` | Whether each file is scanned before it is parsed |
| `async.*` | The imports on a worker: the transport, where the files wait |
| `export.*` | Where the exporter reads images and writes archives |
| `svg.*` | What an SVG image keeps |
