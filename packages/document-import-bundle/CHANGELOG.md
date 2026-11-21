# Changelog

All notable changes to `phil/document-import-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- The `DocumentImporter`, `DocumentExporter` and `SvgSanitizer` services, the importer with the configured limits,
  style map and media store, the exporter with the configured media source and temporary directory, the sanitizer
  with the configured policy.
- `PhilDocumentImportBundle` and the `phil_document_import` configuration: `media` — the store, `auto`, `library` or
  `folder`, the prefix of the folder store, the collection of the media library —, `formats` read and written,
  `limits` — sizes once decoded, depth, objects of a PDF —, `styles`, the block each paragraph style becomes, the
  title of the `report`, `scan` — `auto`, `on` or `off` —, `async` — the transport, the directory or the store of the
  files waiting for the worker —, the `export` media source and temporary directory, and `svg` — the limits of size,
  depth and elements, raster images in `data:` addresses, animation and the schemes of links —, each value checked;
  `phil/media-bundle` and `phil/upload-scan-bundle` found by their names in the kernel's list of bundles.
- `DocumentImports`: imports only the accepted formats, refusing any other before a reader opens it, refuses an
  upload larger than the limits before reading it and any document larger than them, Markdown and HTML included,
  reads the format of an upload from its client name, hands each file to the `DocumentScreening` before it is parsed,
  dispatches `DocumentImported` with the result, its report and the claim of an import on a worker, adds the report
  to the document under the configured title, accepts a file by its size and format alone with `accept()`, gives the
  report title, and exports only the accepted formats.
- `UploadScanScreening`: each file inspected by the scanner of `phil/upload-scan-bundle` under the context
  `document_import` before it is parsed, an infected file or one that could not be checked refused with
  `InvalidDocument`, registered when that bundle is.
- `DocumentImportQueue`, `ImportDocumentHandler` and the `ImportDocument` message: a file accepted at once by its size
  and format, kept in `PendingImports`, imported on a Messenger worker from a message that carries its claim alone,
  `DocumentImported` or `DocumentImportFailed` dispatched with the claim, the claim released once the library
  answered and kept for the next try on any other error; `ImportDocument` routed to the configured transport.
- `FilesystemPendingImports`: the files waiting for the worker in a directory readable by their owner only, under
  claims of 32 random hexadecimal digits that never reach outside it.
- `@PhilDocumentImport/report.html.twig`: the report of an import as an HTML fragment, under the configured title
  through the Twig function `document_import_report_title()`, or a title, heading level and identifier given.
- `MediaLibraryStore`: the images of an imported document uploaded to the media library of `phil/media-bundle`, in
  the configured collection, created the first time, with their text alternative, and shown at the address of the
  `phil_document_import.media_address` closure, the URL of `media_url()` by default.
- The Flex recipe in `recipe/0.1`: the bundle registered and `config/packages/phil_document_import.yaml`, the media store
  and the scan left to `auto`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
