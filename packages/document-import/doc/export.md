# Exporting

`DocumentExporter` takes the way back: a UJO document written as a DOCX or an ODT file, in the styles a word
processor knows, so that a person can edit it in their office suite and the importer reads it back as the same
blocks.

```php
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\MediaMap;

$exported = new DocumentExporter(new MediaMap($imported->media))
    ->export($document, Format::Docx, ['title' => 'Chapter One', 'creator' => 'Ann Writer']);

file_put_contents('chapter-one.'.$exported->extension(), $exported->bytes);
header('Content-Type: '.$exported->mediaType());
```

`export()` takes the document, `Format::Docx` or `Format::Odt` — any other format throws `ExportFailed` —, and the
metadata to write, under the keys an import gives: `title`, `subject`, `creator`, `keywords`, `description`,
`language`, and `created` and `modified` in ISO 8601. `Exported` holds the format, the bytes of the file and the
report of what it has no place for; `mediaType()` and `extension()` name the file.

## Blocks

| UJO | DOCX | ODT |
|---|---|---|
| `paragraph` | `Normal`, aligned by its `alignment` tune | `Text body`, aligned through an automatic style |
| `header` | `Heading 1` to `Heading 6`, with their outline levels | `text:h` in `Heading 1` to `Heading 6` |
| `list` | A numbering instance per list, nested to nine levels, its counter and first number; a checklist's items marked `☑` or `☐` | `text:list` with its own list style |
| `quote` | `Quote`, its caption on a line of its own after a dash | `Quotations`, the same way |
| `code` | `Code`, its lines separated by line breaks | `Preformatted Text` |
| `table` | `Table Grid`, every row as wide as the widest, the heading row repeated on each page | `table:table`, its heading row in `table:table-header-rows` |
| `image` | An inline picture with its text alternative, at 96 pixels an inch and no wider than 6 inches; its caption in `Caption` | A `draw:frame` with its `svg:title` and `svg:desc`; its caption in `Caption` |
| `footnote` and its reference | A footnote where it is first referenced | A `text:note` where it is first referenced |
| `toc` | Word's table of contents control and its `TOC` field, which Word fills when the file opens | `text:table-of-content`, which the suite fills on update |
| `pageBreak` | A page break | A paragraph that breaks before |
| `delimiter` | `* * *`, centred | The same |
| `warning` | Its title in bold and its message, in `Quote` | In `Quotations` |
| `linkTool`, `attaches` | A paragraph with the link, its title as text | The same |
| `embed` | A paragraph with the link to its source, for `http` and `https` sources | The same |

The text keeps bold, italic, underline, strikethrough, highlight, inline code — the `Code Char` style of DOCX, the
`Source Text` style of ODT —, exponents, line breaks and links to `http`, `https`, `mailto` and `tel` addresses; a
link to any other address keeps its text. A link inside a DOCX footnote is written as its text followed by its address.
A footnote referenced twice is written at its first reference, and a reference to a footnote the document does not
hold stays its number in exponent; a footnote never referenced is written at the end, in `Caption`.

## Images

The exporter never fetches an address. The images come from the application's `Contract\MediaSource`, which answers
the bytes behind an image block's address, or `null`; `MediaMap` is a source over images in memory, such as the media
of an import. PNG, JPEG and GIF images go into both formats. An SVG image goes into an ODT once sanitized by
`phil/svg-sanitizer`; a DOCX cannot hold it without a raster fallback, so it is reported. An image the source does not
hold, or of another type, is reported; when its address is `http` or `https`, a paragraph links to it.

## What is reported

The `Report` of `Exported` uses the same kinds as an import: `block` with the type of a block that has no place in a
word processing document — `raw`, a type the exporter does not know, a link to an address that is not `http`,
`https` or `mailto` — and `image` with `not fetched` or the media type of an image it cannot write.

## The file

Every entry of the archive carries one fixed date, so the same document gives the same bytes. On a PHP with
`ZipArchive::openString()` and `ZipArchive::closeString()`, the archive is written in memory and no file is made;
otherwise it is written through a temporary file, in `$temporaryDirectory` or the system's temporary directory,
removed before `export()` returns. Both ways give the same bytes. An ODT starts with its `mimetype` entry, stored, as
the format asks.
