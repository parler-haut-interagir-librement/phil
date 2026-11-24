# Changelog

All notable changes to `phil/document-import` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- Requires the latest minor of each dependency (`phil/markup` 0.3).

## 0.1.0-alpha - 2026-11-20

### Added

- `Format`: DOCX and its templates, ODT and its templates, flat ODT, RTF, PDF, Markdown and HTML, known from a file
  name or detected from the bytes — an OpenDocument text by its `mimetype` entry, another ZIP archive as Office Open
  XML, `%PDF-`, `{\rtf`, an XML `office:document` of a text, text that starts with `<` as HTML; Word 97–2003
  documents and password-protected Office files, named `.doc` or `.dot` or by the signature of a compound file,
  refused.
- `DocumentImporter`: a file or its contents imported into a UJO document checked by `phil/ujo`, with its metadata,
  its images and its report; the Markdown and HTML readers, the normalizer, the media store, the limits, the SVG
  sanitizer and the style map given or left to their defaults.
- `Limits`: the largest entry, file and decoded stream, the largest archive or file — Markdown and HTML
  included — and all its streams, the deepest nesting, the most PDF objects and the most operations reading the
  pages of a PDF — forms counted each time they are drawn, links once per run they cover —; declared sizes checked
  before reading; XML without network access nor document type, an empty entry and a document type refused before
  parsing; a corrupted entry refused.
- DOCX read by the library itself: the main part through the package relationships; paragraphs, headings and titles by
  style name or outline level, quotations, code blocks, alignment; bold, italic, underline, strikethrough, highlight,
  exponents and inline code, from run properties and character styles; hyperlinks and `HYPERLINK` fields, the
  innermost winning; field instructions hidden, `TOC` fields and Word's table of contents controls as `toc` blocks;
  line and page breaks; lists from the numbering, nested, ordered or not, with their counter and first number;
  tables with their header row, spanned and continued cells; footnotes and endnotes numbered in their own sequences;
  DrawingML and VML images, embedded or linked; tracked insertions kept; core properties as metadata; strict Office
  Open XML read through its transitional namespaces; the start of a list from its numbering instance; the title of
  Word's table of contents control.
- ODT read by the library itself: paragraphs, headings, titles, quotations, code, alignment and page breaks by style;
  character formatting by text style and its parents; links; lists and their styles, nested, an item's paragraphs
  joined; tables with header rows, repeated rows and cells, covered cells; footnotes and endnotes; sections and tables
  of contents; images from the archive, embedded in Base64 or linked; metadata; flat ODT (`.fodt`) by the same
  reader, its scripts reported.
- RTF read by the library itself: groups and their character formatting; paragraphs, headings, quotations and code by
  the style sheet's names and the outline level, alignment; lists by `\ls` and `\ilvl`, ordered by their marker;
  tables with their header row; footnotes and endnotes; `HYPERLINK` and `TOC` fields; PNG and JPEG pictures with their
  description; Unicode with its fallback characters and the Windows, DOS and Mac code pages; the document information
  as metadata; headers and footers, comments, objects, shapes, hidden text and colours reported.
- PDF read by the library itself, without external program: cross-reference tables and streams, object streams, a
  damaged file scanned; Flate with its predictors, LZW, ASCII hexadecimal and base-85, run-length; the text state and
  matrices of the content, form XObjects; `ToUnicode` CMaps, encodings and differences, widths; lines gathered into
  headings by size, paragraphs across pages, nested lists, hyphenated words joined, bold, italic, exponents and links;
  running headers and artifacts left out; JPEG and 8-bit images taken out; annotations and forms reported; encrypted
  files refused; the document information as metadata.
- The description of a DOCX or ODT image, or else its title, is the `alt` of its `image` block, as written and cut to
  1000 characters; the caption stays empty. An image in a table cell, a list item or a note becomes an image block
  after the table, the list or the paragraph of the note.
- `StyleMap` and `StyleRole`: the application's paragraph styles, by name or identifier, mapped to a paragraph, a
  heading, a quotation, code or nothing, before the built-in rules, in DOCX, ODT, flat ODT and RTF.
- Markdown and HTML through the readers of `phil/markup`; the images of an HTML paste written in `data:` addresses
  taken out as media.
- `Media`, `MediaStore` and `MediaFolder`: the images taken out, their media type read from their bytes, and the
  address each image block shows, under a prefix by default; a file name that always ends with an extension of the
  type the bytes show; every SVG image sanitized by `phil/svg-sanitizer` before
  it is stored, wherever the document holds it — body, table, note —, an image refused whole left out; two different
  images of one name told apart by a number.
- `Report`, `Omission` and `Omitted`: what was left out — comments, tracked deletions, headers and footers, text
  boxes, shapes and charts, equations, embedded objects, images that cannot be shown, nested tables, merged cells,
  internal links, hidden text, colours, fonts and sizes, macros, HTML elements, the parts of SVG images the
  sanitizer removed, styles left out by the map, PDF annotations and forms — by kind and detail, counted and
  described; as sentences, and as a `warning` block rendered with the document by `Imported::documentWithReport()`.
- `DocumentExporter`, `Exported`, `Contract\MediaSource` and `MediaMap`: a UJO document written as DOCX or ODT —
  paragraphs, headings, lists, quotations, code, tables, images, footnotes, links, page breaks, tables of contents,
  warnings and links — in the styles a word processor knows, read back as the same blocks; images from the
  application, SVG sanitized for ODT; blocks and images without a place reported; the same document giving the same
  bytes.
- Archives opened and written in memory with `ZipArchive::openString()` and `ZipArchive::closeString()` on a PHP that
  has them, through a temporary file removed at once otherwise, the same bytes either way.
- `DocumentImportException`, `InvalidDocument`, `UnsupportedFormat`, `LimitExceeded` and `ExportFailed`.
