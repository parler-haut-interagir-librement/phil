# Reference

| Class | Role |
|---|---|
| `Phil\DocumentImportBundle\PhilDocumentImportBundle` | The bundle: the `phil_document_import` configuration and its services |
| `Phil\DocumentImportBundle\Application\DocumentImports` | Imports the accepted formats, refuses a file larger than the limits before reading it and bytes larger than them in every format, screens each file before it is parsed, dispatches `DocumentImported`, adds the report under the configured title, exports the accepted formats |
| `Phil\DocumentImportBundle\Application\DocumentImportQueue` | Accepts a file as `DocumentImports` would, keeps it in `PendingImports` and dispatches `ImportDocument` with its claim |
| `Phil\DocumentImportBundle\Application\ImportDocumentHandler` | Handles `ImportDocument`: imports the file kept under the claim, dispatches `DocumentImported` or `DocumentImportFailed`, releases the claim |
| `Phil\DocumentImportBundle\Application\PendingImport` | A file waiting for the worker: `$contents`, `$format`, `$filename` |
| `Phil\DocumentImportBundle\Application\Message\ImportDocument` | The message of an import on a worker: `$claim`, 1 to 255 letters, digits, `.`, `_` or `-` (`ImportDocument::CLAIM`) |
| `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` | `screen(string $contents, string $filename): void`, throwing `InvalidDocument` to refuse a file |
| `Phil\DocumentImportBundle\Application\Contract\PendingImports` | `keep(PendingImport): string`, `take(string $claim): ?PendingImport`, `release(string $claim): void` |
| `Phil\DocumentImportBundle\Domain\Event\DocumentImported` | The event of an import: `$imported`, the result and its report, `$filename`, the name the file came with, and `$claim`, the claim of an import on a worker |
| `Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed` | A file imported on a worker and refused: `$claim`, and `$reason`, the `DocumentImportException` |
| `Phil\DocumentImportBundle\Infrastructure\DependencyInjection\DocumentImportServices` | Registers the services; their identifiers as constants |
| `Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore` | The `MediaStore` of `phil/document-import` over the media library of `phil/media-bundle` |
| `Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening` | The `DocumentScreening` over the scanner of `phil/upload-scan-bundle`, under the context `document_import` |
| `Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports` | The `PendingImports` over a directory, its claims 32 random hexadecimal digits |
| `Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension` | The Twig function `document_import_report_title()` |

| Method of `DocumentImports` | Returns |
|---|---|
| `importFile(string $path, ?Format $format = null, ?string $filename = null)` | `Imported`; the format given, or detected from `$filename` — the base name of the path by default — and the bytes |
| `import(string $contents, ?Format $format = null, ?string $filename = null, ?string $claim = null)` | `Imported`; `$claim` given to `DocumentImported` |
| `accept(string $contents, ?Format $format = null, ?string $filename = null)` | The `Format` of a file the application accepts, by its size and format alone, nothing read |
| `reportTitle()` | `report.title` |
| `documentWithReport(Imported $imported)` | The `Document` with the report as a last `warning` block titled by `report.title`, or alone when nothing was left out |
| `export(Document $document, Format $format, array $metadata = [])` | `Exported` |

| Method of `DocumentImportQueue` | Returns |
|---|---|
| `submit(string $contents, ?Format $format = null, ?string $filename = null)` | The claim the result will carry |

| Constant | Value |
|---|---|
| `PhilDocumentImportBundle::EXPORTS` | `['docx', 'odt']`, the formats an export may write |
| `DocumentImportServices::SVG_POLICY` | `phil_document_import.svg_policy` |
| `DocumentImportServices::MEDIA_ADDRESS` | `phil_document_import.media_address` |

The services are listed in [Configuration](configuration.md).
