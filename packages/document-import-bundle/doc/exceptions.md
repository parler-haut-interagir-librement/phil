# Exceptions

The bundle has no exception of its own.

| When | What is thrown |
|---|---|
| The container is built with a value the configuration refuses, or with `media.store: library` and no `PhilMediaBundle` | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` |
| `DocumentImports` is given a file whose format is outside `formats.import`, or a Word 97–2003 binary document | `Phil\DocumentImport\Exception\UnsupportedFormat` |
| `DocumentImports` is given a file or bytes larger than `limits.total_bytes`, whatever their format | `Phil\DocumentImport\Exception\LimitExceeded` |
| `DocumentImports::export()` is asked for a format outside `formats.export` | `Phil\DocumentImport\Exception\ExportFailed` |
| A file cannot be read, is not what its format says, cannot be detected, or exceeds the limits | The exceptions of `phil/document-import`, all `Phil\DocumentImport\Exception\DocumentImportException` |
| The media library refuses an image, or the temporary file of an upload cannot be written | `Phil\MediaBundle\Domain\Exception\InvalidMedia` |

Sanitizing an SVG image never throws: an image the sanitizer refuses whole is left out and reported. A listener of
`DocumentImported` that throws stops the import at that point; its exception reaches the caller.
