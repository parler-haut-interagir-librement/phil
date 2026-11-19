# phil/document-import-bundle

**Word processing documents and PDFs imported in a Symfony application, their images sanitized and kept in the media
library, and documents exported back to DOCX or ODT.**

phil/document-import-bundle puts `phil/document-import` in a Symfony application. It registers the
`DocumentImporter` and the `DocumentExporter` as services, with the limits, the style map and the policy of the
`SvgSanitizer` every SVG image goes through, and gives the importer a media store: the media library of
`phil/media-bundle` when that bundle is registered — each image uploaded as a medium, in a collection of the
configuration, with its text alternative —, or a folder prefix otherwise. `DocumentImports` reads only the formats
the application accepts — DOCX, strict OOXML included, ODT, flat ODT, RTF, PDF, Markdown, HTML —, refuses an upload
larger than the limits before reading it, has each file scanned by `phil/upload-scan-bundle` before it is parsed when
that bundle is registered, and gives the report of what each import left out as an event, a block of the document or
a Twig fragment. `DocumentImportQueue` imports on a Messenger worker, with messages that carry a claim and nothing
else: the file waits in a store, never in the message.

It is for any Symfony application that receives texts written elsewhere: a CMS that takes manuscripts, a tool that
lays out books from the chapters of their authors, a wiki that imports old pages. A controller asks for the importer,
imports the uploaded file, and gets a UJO document whose image blocks point to media already kept.

It is a thin bundle built on `symfony/config` and `symfony/dependency-injection`, and dispatches through
`psr/event-dispatcher`. `phil/media-bundle`, `phil/upload-scan-bundle`, `symfony/messenger` and Twig are optional:
each part that uses one is registered only when it is installed or enabled.

The design is small: `PhilDocumentImportBundle` reads the configuration, `DocumentImportServices` registers the
services, `DocumentImports` applies the accepted formats and the screening and dispatches `DocumentImported`,
`DocumentImportQueue` and `ImportDocumentHandler` import on a worker, and `MediaLibraryStore` keeps the images in the
media library.

## Status

| Part | State |
|---|---|
| `DocumentImporter`, `DocumentExporter` and `SvgSanitizer` as services; the limits, the style map and the SVG policy configured | Implemented and tested |
| `DocumentImports`: the formats accepted for import and export, uploads checked against the limits, the report title | Implemented and tested |
| `DocumentImported`, the report of each import dispatched as an event | Implemented and tested |
| Images kept in `phil/media-bundle`, in a configured collection, with their text alternative | Implemented and tested |
| Images under a folder prefix, without the media library | Implemented and tested |
| Each file scanned by `phil/upload-scan-bundle` before it is parsed | Implemented and tested |
| The report as a Twig fragment, `@PhilDocumentImport/report.html.twig` | Implemented and tested |
| Imports on a Messenger worker, the message carrying a claim, the file in a directory or a store of the application | Implemented and tested |
| An upload form and a controller that imports | Not started: the application's |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle. |
| [Configuration](doc/configuration.md) | Every option, and the services it gives. |
| [Media](doc/media.md) | Where the images go: the media library, a folder, a store of the application's own. |
| [Importing](doc/importing.md) | Importing in a controller, the scan before parsing, the report shown to the author, the style map, exporting. |
| [Imports on a worker](doc/worker.md) | The queue, its message and its claims, where the files wait, the events of the result. |
| [Extension points](doc/extension-points.md) | The services and options an application replaces or relies on. |
| [Exceptions](doc/exceptions.md) | What the bundle and the libraries throw. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/document-import-bundle
```

```yaml
# config/packages/phil_document_import.yaml
phil_document_import:
    media:
        collection: manuscripts
    formats:
        import: [docx, odt, rtf]
    styles:
        'Chapter Title': heading-1
        'Author Note': omit
    svg:
        max_bytes: 1048576
```

```php
public function __invoke(Request $request, DocumentImports $imports): Response
{
    $file = $request->files->get('manuscript');
    $imported = $imports->importFile($file->getPathname(), filename: $file->getClientOriginalName());
    // $imported->document: the UJO document; $imported->report: what was left out;
    // $imports->documentWithReport($imported): the document with the report as a last block.
}
```

```twig
{% include '@PhilDocumentImport/report.html.twig' with {report: imported.report} %}
```

## Requirements

PHP 8.5 or later with `ext-sodium`, Symfony 8.2, `phil/document-import`, `phil/svg-sanitizer`, `phil/ujo` and
`psr/event-dispatcher`; `phil/media-bundle`, for the images kept in the media library; `phil/upload-scan-bundle`, for
the antivirus; `symfony/messenger`, for the imports on a worker; Twig, for the report fragment.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
