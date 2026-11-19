# Importing

## The importer

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\StyleMap;
use Phil\SvgSanitizer\SvgSanitizer;

$importer = new DocumentImporter(
    media: new MediaFolder('media/'),   // where image blocks point
    markdown: null,                     // a phil/markup reader, MarkdownReader by default
    html: null,                         // a phil/markup reader, HtmlReader by default
    normalizer: null,                   // the phil/ujo normalizer of DOCX and ODT documents
    limits: new Limits(),
    svg: new SvgSanitizer(),            // the phil/svg-sanitizer sanitizer of SVG images
    styles: new StyleMap(),             // the application's paragraph styles and their blocks
);

$imported = $importer->importFile('/tmp/upload.docx');
$imported = $importer->import($bytes, filename: 'notes.md');
$imported = $importer->import($bytes, Format::Odt);
```

`import()` takes the contents of a file; `importFile()` reads a path and passes its base name. The format is the one
given, otherwise the one the name announces, otherwise the one the bytes show. The [style map](styles.md) tells the
importer what the application's own paragraph styles become.

## Formats

| `Format` | Extensions | Detected from the bytes | Read by |
|---|---|---|---|
| `Docx` | `.docx`, `.docm`, `.dotx`, `.dotm` | A ZIP archive that is not an OpenDocument text | This library |
| `Odt` | `.odt`, `.ott` | A ZIP archive whose first entry is the `mimetype` of an OpenDocument text, stored | This library |
| `Fodt` | `.fodt` | An XML `office:document` whose `office:mimetype` is that of a text, in its first 4 KiB | This library |
| `Rtf` | `.rtf` | `{\rtf`, after a byte order mark and blank space | This library |
| `Pdf` | `.pdf` | `%PDF-` | This library, see [PDF](pdf.md) |
| `Markdown` | `.md`, `.markdown`, `.mdown`, `.mkd` | Never: Markdown is any text | `phil/markup` |
| `Html` | `.html`, `.htm`, `.xhtml` | Text starting with `<`, after a byte order mark and blank space | `phil/markup` |

`Format::fromFilename()` answers the format of a name or `null`; `Format::detect()` answers the format of a file or
throws `UnsupportedFormat`. The macros of a `.docm` are never run: they are reported. A Word 97–2003 document — named
`.doc` or `.dot`, or a compound file whatever its name — and an Office document protected by a password are refused
with `UnsupportedFormat`, even when a format is given: see [Word processing documents](word-processing.md).

## The result

| Property of `Imported` | Content |
|---|---|
| `format` | The `Format` read |
| `document` | The `Phil\Ujo\Document`, checked and in canonical form; blocks numbered `b1`, `b2`… in reading order |
| `metadata` | The metadata of the file (below) |
| `media` | The images taken out, `Media` by the address their image blocks show |
| `report` | The `Report` of what was left out |

`Imported::documentWithReport()` gives the document with the report as a last `warning` block, so that a reader sees
what was left out with the text: see [The report](report.md).

The metadata of a DOCX are its core properties, those of an ODT its `meta.xml` — or the `office:meta` of a flat ODT —,
those of an RTF its `\info`, those of a PDF its document information and the language of its catalog, under the same
keys when they are present: `title`, `subject`, `creator` (the initial author of an ODT, not its last editor),
`keywords` (joined by commas), `description`, `language`, `created`, `modified` — the dates as the file writes them. A
Markdown file gives its front matter, an HTML page its title and named `<meta>`.

## Limits

`Limits` bounds what a document may hold, and refuses with `LimitExceeded` what goes past it:

| Limit | Default | Bounds |
|---|---|---|
| `entryBytes` | 64 MiB | One entry of an archive once uncompressed; a flat ODT; one PDF stream once decoded |
| `totalBytes` | 256 MiB | An archive once uncompressed; an RTF, PDF, Markdown or HTML file; all the streams of a PDF once decoded |
| `depth` | 100 | The nesting of RTF groups, of PDF arrays, dictionaries and page tree |
| `objects` | 500 000 | The objects a PDF declares |
| `operations` | 1 000 000 | The work of reading the pages of a PDF: each content operator, those of a form each time it is drawn, and each run of text a link is checked against |

The sizes an archive declares are checked before an entry is read, and an entry whose bytes do not match its declared
size or checksum cannot be read: a compression bomb is refused before it is inflated. A PDF stream is inflated no
further than its limit. A PDF of a few kilobytes whose forms draw one another a thousand times over asks for billions
of operations: it is refused once it goes past `operations`, within seconds.

```php
new DocumentImporter(limits: new Limits(entryBytes: 8 * 1024 * 1024, totalBytes: 32 * 1024 * 1024, depth: 50));
```

XML — the entries of an archive, a flat ODT — is parsed without network access, and an empty entry or one that
declares a document type — which no office document does — is refused before it is parsed, which closes the door to
entity expansion (XXE and its cousins).
