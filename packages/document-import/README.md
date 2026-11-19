# phil/document-import

**Word processing documents, PDF, Markdown and HTML turned into UJO documents, with a report of what did not fit — and
UJO documents written back as DOCX and ODT.**

phil/document-import imports the texts people write elsewhere into UJO (Universal JSON Output), the block document
format of `phil/ujo`. It reads Office Open XML documents (`.docx`, transitional or strict), OpenDocument texts (`.odt`,
and flat `.fodt`) and Rich Text Format (`.rtf`) itself — paragraphs and headings, bold, italic, underline,
strikethrough, highlight and exponents, inline code, quotations and code blocks, nested lists with their numbering,
tables, footnotes and endnotes, links, tables of contents, page breaks, and images taken out as media —, reads the text
and simple structure of a PDF — headings, paragraphs, lists, links and images, from the sizes and places of its lines,
running headers left out —, and hands Markdown and HTML to the readers of `phil/markup`, taking the images an HTML paste
carries in `data:` addresses out as media too. A table maps the application's own paragraph styles to the blocks they
become. Word 97–2003 documents are recognised and refused. Every SVG image is sanitized by `phil/svg-sanitizer` before
it is stored: no script, no event handler, no reference to another file. What a UJO document has no place for —
comments, tracked deletions, headers and footers, text boxes, shapes and charts, equations, colours and fonts, merged
cells, macros — is left out and counted in a report the application can show, or render with the document as a last
block. The way back writes a UJO document as a DOCX or an ODT file in the styles a word processor knows, which imports
again as the same blocks.

It is for any PHP application that receives texts written in a word processor or an editor: a CMS that takes
manuscripts, a tool that lays out books from the chapters of their authors, a wiki that imports old pages, a forum that
accepts pasted documents. One call imports a file — its format given, or detected from its name and bytes — into a
checked UJO document, its metadata, its images and its report; the application stores the images where it wants.
One call exports a document, its images given by the application.

It is a plain PHP library, without any external program: the archives are read and written with PHP's `zip` extension,
in memory where `ZipArchive::openString()` exists, and their XML with its `Dom\XMLDocument`, PDF streams with its
`zlib` extension, with limits on what a document may hold once uncompressed and how deep it may nest. ECMA-376,
OpenDocument 1.3, the RTF 1.9 specification and ISO 32000 are its references; no code of another importer is used.
`phil/ujo` checks the document, `phil/markup` reads Markdown and HTML, and `phil/svg-sanitizer` sanitizes SVG images.

The design is small: `DocumentImporter` imports, `Format` names and detects the formats, `Imported` holds the
result, a `MediaStore` gives each image its address — `MediaFolder` by default —, a `StyleMap` names the
application's styles, and `Report` lists each `Omission`. `DocumentExporter` exports, its images from a
`Contract\MediaSource` — `MediaMap` over images in memory —, into `Exported`.

## Status

| Part | State |
|---|---|
| Format detection from the file name and the bytes | Implemented and tested |
| DOCX: paragraphs, headings and titles by style or outline level, quotations, code, alignment | Implemented and tested |
| DOCX: character formatting, character styles, hyperlinks and `HYPERLINK` fields, tables of contents, page breaks | Implemented and tested |
| DOCX: lists from the numbering, nested; tables with header rows and spans; footnotes and endnotes | Implemented and tested |
| DOCX: images, DrawingML and VML, embedded or linked; core properties as metadata | Implemented and tested |
| ODT: paragraphs, headings, titles, quotations, code, alignment, page breaks, character formatting by style | Implemented and tested |
| ODT: links, lists, tables with header rows, repeated and covered cells, notes, tables of contents, sections | Implemented and tested |
| ODT: images in the archive, embedded in Base64 or linked; metadata | Implemented and tested |
| Markdown and HTML through `phil/markup`, HTML images in `data:` addresses as media | Implemented and tested |
| SVG images sanitized by `phil/svg-sanitizer` before they are stored, what was removed reported | Implemented and tested |
| Report of what was left out, as rows, sentences or a block rendered with the document | Implemented and tested |
| Limits against compression and nesting bombs; XML without network nor document type (XXE) | Implemented and tested |
| Strict Office Open XML, read through its transitional namespaces | Implemented and tested |
| Flat ODT (`.fodt`) by the ODT reader | Implemented and tested |
| RTF: paragraphs, styles, character formatting, lists, tables, notes, fields, PNG and JPEG pictures, information | Implemented and tested |
| PDF: cross references and object streams, filters, fonts and CMaps, headings, paragraphs, lists, links, images, running headers left out | Implemented and tested |
| Style map: the application's paragraph styles to block types, before the built-in rules | Implemented and tested |
| Images in a table cell, a list item or a note, written after their block; two images of one name told apart | Implemented and tested |
| Export to DOCX and ODT, read back as the same blocks, images from the application | Implemented and tested |
| Word 97–2003 `.doc` and password-protected Office files recognised and refused | Implemented and tested |
| Fuzz targets for every reader | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Importing](doc/importing.md) | `DocumentImporter`, the formats and their detection, `Imported` and the metadata, the limits. |
| [Word processing documents](doc/word-processing.md) | What DOCX, strict DOCX, ODT, flat ODT and RTF become, construct by construct; Word 97–2003 documents. |
| [PDF](doc/pdf.md) | What a PDF becomes: objects, text, fonts, lines into blocks, images, what is left out. |
| [Style map](doc/styles.md) | `StyleMap` and `StyleRole`: the application's paragraph styles and the blocks they become. |
| [Exporting](doc/export.md) | `DocumentExporter`: a UJO document as DOCX or ODT, its images, what is reported. |
| [Markdown and HTML](doc/markup.md) | The readers of `phil/markup`, choosing them, and the images of an HTML paste. |
| [Media](doc/media.md) | `Media`, `MediaStore`, `MediaFolder`, SVG images sanitized, and storing the images. |
| [The report](doc/report.md) | `Report`, `Omission`, `Omitted`, and rendering the report with the document. |
| [Extension points](doc/extension-points.md) | The media store and source, the style map, the readers, the SVG sanitizer and the limits. |
| [Exceptions](doc/exceptions.md) | `DocumentImportException` and its four exceptions. |
| [Testing](doc/testing.md) | The library's tests, the mutation bar, and how to test code that imports documents. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/document-import
```

```php
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\MediaMap;
use Phil\Ujo\Ujo;

$imported = new DocumentImporter(new MediaFolder('/uploads/manuscript-42/'))->importFile('chapter-one.docx');

foreach ($imported->media as $address => $media) {
    file_put_contents('public'.$address, $media->contents);
}

$json = new Ujo()->encode($imported->document);

foreach ($imported->report->omissions() as $omission) {
    echo $omission->count, ' × ', $omission->what->description(), PHP_EOL;
}

$docx = new DocumentExporter(new MediaMap($imported->media))->export($imported->document, Format::Docx);
file_put_contents('chapter-one-edited.docx', $docx->bytes);
```

## Requirements

PHP 8.5 or later, with the `dom`, `libxml`, `mbstring`, `sodium`, `zip` and `zlib` extensions; `phil/ujo`,
`phil/markup` and `phil/svg-sanitizer`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
