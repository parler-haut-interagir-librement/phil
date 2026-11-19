# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | As `phil/document-import` |
| `ext-mbstring` | The alternative text of a medium cut on characters |
| `phil/document-import` | The importer and the exporter |
| `phil/svg-sanitizer` | The sanitizer of SVG images |
| `phil/ujo` | The documents imported and exported |
| `psr/event-dispatcher` | The interface `DocumentImported` is dispatched through |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and the services |
| `ext-sodium` | The claims of the files waiting for the worker |
| `phil/media-bundle`, optional | The images kept in the media library |
| `phil/upload-scan-bundle`, optional | Each file scanned by an antivirus before it is parsed |
| `symfony/messenger`, optional | The imports on a worker |
| `twig/twig`, optional | The report of an import as an HTML fragment |

## Installing

```sh
composer require phil/document-import-bundle
```

With Symfony Flex, the recipe enables the bundle and writes `config/packages/phil_document_import.yaml`, the media
store and the scan left to `auto`; without Flex, add it to `config/bundles.php`:

```php
return [
    // ...
    Phil\DocumentImportBundle\PhilDocumentImportBundle::class => ['all' => true],
];
```

With no configuration, `DocumentImports` reads every format of the library and writes DOCX and ODT, with the limits
and the SVG policy of the libraries and an empty style map, and keeps the images in the media library when
`PhilMediaBundle` is registered, or answers `media/` and their file name otherwise, and scans each file before it is
parsed when `PhilUploadScanBundle` is registered. The report of each import is
dispatched when the application has an event dispatcher, as a full-stack Symfony application does.
