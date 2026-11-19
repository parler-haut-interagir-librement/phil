# Configuration

```yaml
# config/packages/phil_document_import.yaml
phil_document_import:
    media:
        store: auto            # auto, library or folder
        prefix: 'media/'       # the folder store: the address before each file name
        collection: ~          # the media library: the key of the collection, created the first time
    formats:
        import: [docx, odt, fodt, rtf, pdf, markdown, html]
        export: [docx, odt]
    limits:
        entry_bytes: 67108864  # an entry of an archive, a stream of a PDF, a flat document or a picture, once decoded
        total_bytes: 268435456 # a file and everything decoded from it together
        depth: 100             # how deep groups, objects and forms may nest
        objects: 500000        # how many objects a PDF may declare
    styles:                    # the block each paragraph style becomes
        'Chapter Title': heading-1
        'Rules Box': quote
        'Author Note': omit
    report:
        title: 'What was not imported'
    scan: auto                 # auto, on or off: phil/upload-scan-bundle before each file is parsed
    async:
        enabled: false         # true, or any key below, turns it on
        transport: ~           # the Messenger transport ImportDocument is routed to
        directory: '%kernel.project_dir%/var/document-imports'
        store: ~               # a service implementing Phil\DocumentImportBundle\Application\Contract\PendingImports
    export:
        media_source: ~        # a service implementing Phil\DocumentImport\Contract\MediaSource
        temporary_directory: ~ # the system's by default
    svg:
        max_bytes: 4194304     # a larger SVG image is refused whole
        max_depth: 64          # an SVG image nested deeper is refused whole
        max_elements: 50000    # an SVG image with more elements is refused whole
        data_images: true      # PNG, JPEG, GIF, WebP and AVIF images in data: addresses
        animations: false      # SMIL animation
        link_schemes: [http, https, mailto]
```

| Option | Checked | Effect |
|---|---|---|
| `media.store` | `auto`, `library` or `folder` | `auto` keeps the images in the media library when `PhilMediaBundle` is registered, under the prefix otherwise; `library` requires the media library; `folder` never uses it |
| `media.prefix` | | The address of an image under the folder store is this prefix followed by its file name |
| `media.collection` | A collection key of `phil/media-bundle`: 1 to 64 lower-case letters, digits, `_` or `-`, starting with a letter | The collection the media go to; none by default |
| `formats.import` | Values of `Phil\DocumentImport\Format`: `docx`, `odt`, `fodt`, `rtf`, `pdf`, `markdown`, `html` | The formats `DocumentImports` reads; any other is refused before a reader opens it. `docx` covers the strict form of Office Open XML, `fodt` flat OpenDocument |
| `formats.export` | `docx`, `odt` | The formats `DocumentImports::export()` writes |
| `limits.entry_bytes`, `limits.total_bytes` | At least 1 | The sizes of `Phil\DocumentImport\Limits`; an upload larger than `total_bytes` is refused before it is read |
| `limits.depth` | At least 1 | How deep RTF groups, PDF objects, page trees and forms may nest |
| `limits.objects` | At least 1 | How many objects a PDF may declare in its cross-reference tables and object streams |
| `styles` | A name of 1 to 255 characters, not only spaces; a value of `Phil\DocumentImport\StyleRole`: `paragraph`, `heading-1` to `heading-6`, `quote`, `code`, `omit` | `Phil\DocumentImport\StyleMap`, read by the DOCX, ODT, flat ODT and RTF readers before their own rules; a style is named as the word processor shows it or by its identifier, case and surrounding spaces aside; `omit` leaves its paragraphs out and reports them |
| `report.title` | A text, not only spaces | The title of the `warning` block `DocumentImports::documentWithReport()` adds, and of `@PhilDocumentImport/report.html.twig` |
| `scan` | `auto`, `on` or `off` | `auto` hands each file to the scanner of `phil/upload-scan-bundle` before it is parsed when `PhilUploadScanBundle` is registered; `on` requires that bundle; `off` never uses it |
| `async.enabled` | | Registers `DocumentImportQueue`, the handler of `ImportDocument` and the store of the files; requires `symfony/messenger` |
| `async.transport` | The name of a transport: 1 to 64 letters, digits, `_`, `.` or `-` | Routes `ImportDocument` to it, before the application's own routing |
| `async.directory` | Not empty | Where `FilesystemPendingImports` keeps the files waiting for the worker |
| `async.store` | The identifier of a service | The `PendingImports` of the application, in place of the directory |
| `export.media_source` | The identifier of a service | The `MediaSource` the exporter reads the bytes of each image from; without one, every image is reported and left out |
| `export.temporary_directory` | | Where the exporter writes each archive before reading it back, on a PHP without `ZipArchive::openString()`; a PHP that has it writes the archive in memory |
| `svg.max_bytes`, `svg.max_depth`, `svg.max_elements` | At least 1 | The limits of `Phil\SvgSanitizer\Policy` |
| `svg.data_images` | | `Policy::withDataImages()` |
| `svg.animations` | | `Policy::withAnimations()` when true |
| `svg.link_schemes` | Lower case; never `javascript`, `vbscript`, `livescript`, `mocha`, `data` nor `file` | `Policy::withLinkSchemes()`; an empty list keeps only fragments |

A Word 97–2003 binary document, or an Office document protected by a password, is refused whatever the list of
formats: the library reads neither.

Keys under `styles` are kept as written: `Rules-Box` and `Rules_Box` name two styles.

## Services

| Service | Class | Public |
|---|---|---|
| `Phil\DocumentImportBundle\Application\DocumentImports` | `DocumentImports` | Yes |
| `Phil\DocumentImport\DocumentImporter` | `DocumentImporter` | Yes |
| `Phil\DocumentImport\DocumentExporter` | `DocumentExporter` | Yes |
| `Phil\SvgSanitizer\SvgSanitizer` | `SvgSanitizer` | Yes |
| `phil_document_import.svg_policy` | `Phil\SvgSanitizer\Policy` | No |
| `Phil\DocumentImport\Limits` | `Limits` | No |
| `Phil\DocumentImport\StyleMap` | `StyleMap` | No |
| `Phil\DocumentImport\MediaFolder` | `MediaFolder` | No |
| `Phil\DocumentImport\MediaStore` | An alias of `MediaLibraryStore` or `MediaFolder` | No |
| `Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore` | `MediaLibraryStore`, with the media library | No |
| `phil_document_import.media_address` | `Closure`, with the media library | No |
| `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` | An alias of `UploadScanScreening`, with the scanner | No |
| `Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening` | `UploadScanScreening`, with the scanner | No |
| `Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension` | `ImportReportExtension`, tagged `twig.extension` | No |
| `Phil\DocumentImportBundle\Application\DocumentImportQueue` | `DocumentImportQueue`, with `async` | Yes |
| `Phil\DocumentImportBundle\Application\ImportDocumentHandler` | `ImportDocumentHandler`, tagged `messenger.message_handler`, with `async` | No |
| `Phil\DocumentImportBundle\Application\Contract\PendingImports` | An alias of `FilesystemPendingImports` or of `async.store`, with `async` | No |
| `Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports` | `FilesystemPendingImports`, with `async` and no `async.store` | No |

`DocumentImporter` and `DocumentExporter` read and write every format the library knows; `DocumentImports` applies
`formats`, the screening and dispatches the report.

`PhilMediaBundle` and `PhilUploadScanBundle` are found by their names in the kernel's list of bundles, whatever the
order of `config/bundles.php`. The `SvgSanitizer` service is the one the importer and the exporter use: a form
that accepts SVG uploads elsewhere in the application sanitizes them with the same policy by asking for it.
