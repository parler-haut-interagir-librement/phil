# PDF

A PDF describes pages, not a document: it places glyphs at positions and draws images, and says nothing of
paragraphs, headings or lists unless it was tagged. The library reads the text and the simple structure the pages
show, itself, without any external program, and reports what it leaves out.

```php
$imported = new DocumentImporter()->importFile('rules.pdf');
```

## What is read

| Part of the file | How |
|---|---|
| Objects | Through the cross-reference tables and streams, newest section first, `Prev` and hybrid files followed; objects in object streams; a file whose cross references are missing or damaged is scanned for its objects |
| Streams | Flate with its PNG and TIFF predictors, LZW, ASCII hexadecimal, ASCII base-85 and run-length; a damaged Flate stream gives what it holds before its damage |
| Pages | The page tree in order, each page with the resources and media box it has or inherits; its content streams joined |
| Text | The text state followed through `BT`, `Tf`, `Td`, `TD`, `Tm`, `T*`, `Tc`, `Tw`, `Tz`, `TL`, `Ts`, `Tj`, `TJ`, `'` and `"`, with the graphics matrix of `q`, `Q` and `cm`; form XObjects entered with their own resources and matrix |
| Characters | A font's `ToUnicode` CMap first; then, for a simple font, its `Differences` by glyph name and its encoding — WinAnsi, MacRoman, Standard; the code of a composite font without CMap read as Unicode |
| Widths | `Widths` and `FirstChar` of a simple font, `W` and `DW` of a composite one: where each string ends, so that the next string on the line follows it or a space comes between them |
| Bold and italic | The font's name — `Bold`, `Black`, `Heavy`, `Semibold`, `Demi`, `Italic`, `Oblique` — and its descriptor's flags and weight |
| Links | Link annotations to an `http`, `https` or `mailto` address put on the text whose middle they cover |
| Metadata | The document information: `title`, `creator` (its author), `subject`, `keywords`, `created` and `modified` in ISO 8601; the `language` of the catalog. Text strings in UTF-16 or UTF-8 after their byte order mark, others read as Windows-1252 |

## From lines to blocks

The strings of a page are gathered into lines by their baseline. A raised, smaller string on a line is an exponent.
Then the lines become blocks from their sizes and positions alone:

- the size most characters have is the body text;
- a larger size that few characters have — no more than a quarter of the body text — makes headings, the largest
  first, up to six levels; a short line alone, all in bold, with no closing punctuation, is a heading below them;
- a line that starts with a bullet (`•`, `◦`, `▪`, `-`, `–`, `*`…) or a number or letter followed by `.` or `)` is a
  list item; the indentation of an item nests it; a change between bullets and numbers starts another list;
- lines of one size close together are one paragraph; a paragraph goes on across a page when its last line does not
  end a sentence and the next page starts in lower case; a word cut by a hyphen at the end of a line is joined again;
- bold, italic, exponents and links are kept inside the text.

A line that comes back near the top or the bottom of most pages — a running header, a footer, a page number, its
digits aside — is left out and reported once. The text of marked-content artifacts, which tagged PDFs use for the same
purpose, is left out as well.

## Images

A JPEG image is taken out as it is, and 8-bit grey or RGB samples are written as a PNG file; an image drawn again —
a logo on every page — is taken once, and an image smaller than 8 pixels — a rule, a dot — is left alone. Other
images — JPEG 2000, CCITT and JBIG2 faxes, CMYK and indexed colours, inline images — are reported.

## Left out and refused

Form fields, stamps, attachments and other annotations, comments and markup annotations, links to places of the
document, running headers, and the images above are reported. An encrypted PDF, even one that opens without password,
is refused with `UnsupportedFormat`: save it again without restrictions. A file that is not a PDF is refused with
`InvalidDocument`.

A scanned page holds an image of its text, not text: it gives no paragraph. The library does no optical character
recognition. Tables, columns side by side and footnotes have no mark in an untagged PDF and come out as paragraphs;
for a faithful import, the document the PDF was made from is the better source.

## Limits

The file is no larger than `$totalBytes`; each stream once decoded no larger than `$entryBytes`, and all of them
together no larger than `$totalBytes`; arrays, dictionaries and the page tree nest no deeper than `$depth`, form
XObjects no deeper than 32; the file declares no more than `$objects` objects; reading its pages takes no more than
`$operations` operations — each content operator, those of a form each time it is drawn, and each run of text a link
annotation is checked against. A loop in the page tree or in the
references ends; nothing of the file is ever run.
