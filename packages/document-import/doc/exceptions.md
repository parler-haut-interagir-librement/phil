# Exceptions

Every exception of the library implements `Phil\DocumentImport\Exception\DocumentImportException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidDocument` | `RuntimeException` | The file cannot be read; the bytes are not a ZIP archive; an entry is corrupted; an entry is not well-formed XML or declares a document type; a DOCX has no main document part or no body; an ODT has no text in `content.xml`; no temporary file can be made; an RTF that does not start with `{\\rtfN`; a PDF that does not start with `%PDF-`; a flat ODT whose root is not the `office:document` of a text |
| `UnsupportedFormat` | `InvalidArgumentException` | The format is not given and neither the name nor the bytes show it; the file is a Word 97–2003 document or an Office document protected by a password; the PDF is encrypted |
| `LimitExceeded` | `RuntimeException` | An entry, an archive, a file or a PDF stream is larger than the `Limits` allow; RTF groups or PDF objects nest deeper; a PDF declares more objects, or asks for more operations to read its pages |
| `ExportFailed` | `RuntimeException` | A document is exported in a format other than DOCX and ODT, or its archive cannot be written |

Each message names the file or the entry, for example `The entry "word/document.xml" is not well-formed XML.`

Two exceptions of the sibling packages can also come through: `Phil\Markup\Exception\InvalidSource` when a Markdown
or HTML reader given to the importer refuses its source (the default readers never do), and the exceptions of
`phil/ujo` when the media store answers an address an image block refuses or a normalizer given does not know a block
type the import writes.

What a document holds that UJO cannot keep is not an exception: it is left out and reported.
