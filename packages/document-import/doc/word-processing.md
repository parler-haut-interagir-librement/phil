# Word processing documents

DOCX, ODT and RTF are read by this library, construct by construct. What a construct becomes is the same in every
format. The paragraph styles go through the application's [style map](styles.md) first.

## Blocks

| Construct | DOCX | ODT | UJO |
|---|---|---|---|
| Paragraph | `w:p` | `text:p` | `paragraph`, with the `alignment` tune when centred, right-aligned or justified |
| Heading | Style named `heading 1` to `heading 9`, or with an outline level (0 to 8) along its `basedOn` chain | `text:h` and its outline level | `header`, levels past 6 written as 6 |
| Title | Style named `Title` | Paragraph style `Title` | `header` of level 1 |
| Quotation | Styles `Quote`, `Intense Quote`, `Block Text` | Paragraph styles `Quotations`, `Quote` | `quote` |
| Code | Styles `HTML Preformatted`, `Source Code`, `Code`; consecutive paragraphs join | Paragraph styles `Preformatted Text`, `Source Text` | `code`, one block per run of lines |
| List | `w:numPr` of the paragraph or of its style, the numbering's level 0 giving the kind | `text:list` and its list style | `list`, nested, `ordered` or `unordered`, its counter and first number |
| Table | `w:tbl`, the first row's `w:tblHeader` making headings | `table:table`, `table:table-header-rows` making headings | `table`, every row as wide as the widest |
| Footnote, endnote | `w:footnoteReference`, `w:endnoteReference` | `text:note` and its class | A reference `<sup data-footnote>` in the text and a `footnote` block at the end, `fn-1`… and `en-1`… |
| Table of contents | A `TOC` field or Word's table of contents control | `text:table-of-content` and its title | `toc`; the entries are left out, the renderer builds them |
| Page break | `w:br w:type="page"` | A paragraph style breaking before | `pageBreak` |
| Image | DrawingML `a:blip` or VML `v:imagedata`, its description or title | `draw:frame` with `draw:image`, its `svg:desc` or `svg:title` | `image`, its `alt` the text alternative, cut to 1000 characters |
| Section, content control | `w:sdt`, `w:customXml`, inserted revisions | `text:section` | Their content |

An image cuts its paragraph: the text before it, the image block and the text after it follow each other. A table
cell, a list item of an ODT and a note hold only text: an image met there becomes an image block after the table, the
list, or the paragraph that refers to the note.

## Text

| Construct | DOCX | ODT | Inline HTML |
|---|---|---|---|
| Bold | `w:b` | `fo:font-weight` bold or 600 and more | `<b>` |
| Italic | `w:i` | `fo:font-style` italic or oblique | `<i>` |
| Underline | `w:u` other than `none` | `style:text-underline-style` other than `none` | `<u class="cdx-underline">` |
| Strikethrough | `w:strike`, `w:dstrike` | `style:text-line-through-style` other than `none` | `<s class="cdx-strikethrough">` |
| Highlight | `w:highlight` other than `none` | `fo:background-color` other than `transparent` | `<mark class="cdx-marker">` |
| Inline code | Character styles `HTML Code`, `Verbatim Char`, `Source Code Char`, `Code Char` | Text styles `Source Text`, `Teletype`, `Example` | `<code class="inline-code">` |
| Exponents | `w:vertAlign` | `style:text-position` | `<sup>`, `<sub>` |
| Link | `w:hyperlink` to an external relationship, `HYPERLINK` fields | `text:a` | `<a href>` |
| Line break | `w:br`, `w:cr` | `text:line-break` | `<br>` |
| Tab, spaces | `w:tab` as a space | `text:tab` as a space, `text:s` as its spaces | Text |

Formatting comes from the run and from its character style with the styles it is based on (DOCX), or from the text
style and its parents, and from the automatic style of the paragraph — its direct formatting — (ODT). The formatting
a paragraph style gives all its text, such as the bold of a heading, is not repeated on the text. When two links
hold the same text, the innermost wins. The instructions of fields are not text; their results are.

## Left out

Each of these is reported, as [the report](report.md) describes: comments and annotations; tracked deletions (the
document is read as if every change were accepted); headers and footers that hold text; text boxes; shapes, charts and
diagrams; equations; embedded objects; images that are missing, linked to a file, or in a format a browser does not
show (EMF, WMF, TIFF); tables inside cells, kept as their text; merged cells, split; links
to places inside the document, which keep their text; hidden text; colours, fonts and sizes; symbol characters of
symbol fonts; macros.

Bookmarks, proofing marks, section properties and soft page breaks are left out without a report: they carry nothing
a reader sees.

## Strict Office Open XML

A DOCX saved as *Strict Open XML* uses the namespaces of ISO/IEC 29500 strict (`http://purl.oclc.org/ooxml/…`)
instead of the transitional ones. The library reads its parts with those namespaces taken as their transitional forms,
so that a strict document gives the same blocks as a transitional one.

## Flat OpenDocument

A flat OpenDocument text (`.fodt`) is one XML file: its `office:document` holds the metadata, the styles, the
automatic styles, the master pages, the scripts and the body that a packaged ODT spreads over `meta.xml`,
`styles.xml` and `content.xml`, and its images are embedded in Base64. It is read by the same reader, construct by
construct, as a packaged ODT; its scripts are reported as macros. The file is parsed like an entry of an archive — no
network, no document type — and is no larger than `Limits::$entryBytes`.

## RTF

Rich Text Format is read token by token: its groups and their character formatting, its control words and symbols,
its destinations.

| Construct | RTF | UJO |
|---|---|---|
| Paragraph | `\par`, `\sect`; `\pard` resets its properties | `paragraph`, aligned by `\qc`, `\qr`, `\qj` |
| Heading, quotation, code | `\sN` and the style sheet's names, as in a DOCX; `\outlinelevelN` of the paragraph or its style | `header`, `quote`, `code` |
| List | `\lsN` and `\ilvlN`; the `\listtext` or `\pntext` marker telling a numbered item from a bullet | `list`, nested |
| Table | `\intbl` paragraphs, `\cell`, `\row`; `\trhdr` on the first row making headings | `table` |
| Footnote, endnote | `\footnote`, `\ftnalt` for an endnote | A reference in the text and a `footnote` block at the end |
| Field | `HYPERLINK "address"` linking its result; `TOC` | A link; `toc` |
| Page break | `\page` | `pageBreak` |
| Picture | `\pict` with `\pngblip` or `\jpegblip`, hexadecimal or `\binN`; its `wzDescription` | `image` |
| Text | `\b`, `\i`, `\ul…`, `\strike`, `\striked1`, `\highlightN`, `\super`, `\sub`; `\uN` with its fallback characters, `\'hh` in the code page of `\ansicpgN`, `\mac` or `\pc`; `\line`, `\tab`, `\~`, `\-`, `\_` and the named quotes and dashes | Inline HTML |
| Information | `\info`: `\title`, `\author`, `\subject`, `\keywords`, `\doccomm` | `title`, `creator`, `subject`, `keywords`, `description` |

Fonts, colour tables, list tables, revision tables, themes and the other destinations that hold no text are skipped,
as is any destination starting with `\*` the reader does not know. Headers and footers that hold text, comments,
embedded objects, shapes and text boxes, hidden text (`\v`), colours, nested tables, links to bookmarks, and
pictures other than PNG and JPEG — EMF, WMF, bitmaps — are reported. The code pages read are those `mbstring` knows —
1251, 1252, 1254, 850, 866 and the East Asian ones — and Mac Roman; any other is read as Windows-1252. Groups nest no
deeper than `Limits::$depth`, the destinations that are not text hold no more than twice `Limits::$entryBytes`, and
the file is no larger than `Limits::$totalBytes`. Unclosed groups and stray braces are tolerated.

## Word 97–2003 documents

A `.doc` file of Word 97 to 2003 is a compound file of binary records, not XML. The library does not read it: a file
named `.doc` or `.dot`, and any file whose bytes start with the signature of a compound file, is refused with
`UnsupportedFormat` and a message that asks for a DOCX, an ODT or an RTF. The same signature marks an Office document
protected by a password, which is refused the same way.
