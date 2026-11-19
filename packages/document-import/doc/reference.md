# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\DocumentImport\DocumentImporter` | `final readonly class` | Imports a file or its contents into a UJO document |
| `Phil\DocumentImport\DocumentExporter` | `final readonly class` | Exports a UJO document as a DOCX or an ODT file |
| `Phil\DocumentImport\Exported` | `final readonly class` | The format, bytes and report of an export |
| `Phil\DocumentImport\Format` | enum | DOCX, ODT, flat ODT, RTF, PDF, Markdown, HTML; known from a name or detected from bytes |
| `Phil\DocumentImport\Imported` | `final readonly class` | The format, document, metadata, media and report of an import; the document with its report |
| `Phil\DocumentImport\StyleMap` | `final readonly class` | The application's paragraph styles and the blocks they become |
| `Phil\DocumentImport\StyleRole` | enum | Paragraph, heading of a level, quotation, code, or left out |
| `Phil\DocumentImport\Contract\MediaSource` | interface | Answers the bytes of an image an export writes |
| `Phil\DocumentImport\MediaMap` | `final readonly class` | A media source over images in memory |
| `Phil\DocumentImport\Media` | `final readonly class` | An image taken out: path, bytes, text alternative, media type |
| `Phil\DocumentImport\MediaStore` | interface | Answers the address of an image block |
| `Phil\DocumentImport\MediaFolder` | `final readonly class` | The default store: a prefix and the file name |
| `Phil\DocumentImport\Report` | `final class` | What an import or an export left out, as rows, sentences or a block |
| `Phil\DocumentImport\Omission` | `final readonly class` | One kind and detail left out, and its count |
| `Phil\DocumentImport\Omitted` | enum | The kinds of things left out, and their description |
| `Phil\DocumentImport\Limits` | `final readonly class` | The largest entry, file and stream, the deepest nesting, the most PDF objects, the most operations reading a PDF |
| `Phil\DocumentImport\Exception\DocumentImportException` | interface | Implemented by every exception of the library |
| `Phil\DocumentImport\Exception\InvalidDocument` | `final class` | A file that is not what its format says |
| `Phil\DocumentImport\Exception\UnsupportedFormat` | `final class` | A format neither given nor detected |
| `Phil\DocumentImport\Exception\LimitExceeded` | `final class` | A document larger, or nested deeper, than the limits |
| `Phil\DocumentImport\Exception\ExportFailed` | `final class` | An export in another format, or an archive that cannot be written |

The classes under `Phil\DocumentImport\Internal` are marked `@internal`: the archive, the builder of blocks, the
inline writer, the DOCX, ODT, RTF and PDF readers and their parts, the DOCX and ODT writers, the preparation of HTML.
They may change without notice.
