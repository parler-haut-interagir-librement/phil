# Navigation

Every book has a navigation document, `nav.xhtml`, with its table of contents, its landmarks and, when the content has
page break markers, its page list. A book may also carry the EPUB 2 table of contents, `toc.ncx`, for older reading
systems.

## Table of contents

By default the table of contents follows the headings of the chapters in `inToc`, from `<h1>` down to the book's depth
(`Book::withTocDepth()`, 3 by default): each heading is an entry nested under the previous heading of a higher level.
A heading without identifier gets one (`heading-1`, `heading-2`…). A chapter without heading at that depth is one
entry, its title, at the level of the book's highest heading.

Headings inside `aside`, `nav`, `blockquote`, `figure`, `details` and `table`, headings with `data-toc="exclude"` and
empty headings stay out of it.

`Book::withToc()` gives the table instead, as `TocEntry` values whose addresses are relative to the package directory:

```php
$book = $book->withToc([
    new TocEntry('Rules', 'chapter-1.xhtml', [
        new TocEntry('Combat', 'chapter-1.xhtml#combat'),
    ]),
    new TocEntry('Appendix', 'appendix.xhtml'),
]);
```

## Landmarks

The landmarks list the cover, when there is one; the table of contents, when the navigation document is in the
reading order (`Book::withNavigationInSpine()`); each chapter with a `LandmarkType`, labelled by its title; and the
start of the content — the chapter marked `LandmarkType::BodyMatter`, or else the first chapter without landmark.

## Page list

A book that follows a printed edition marks where each printed page starts, so that a reader can go to a page given by
someone holding the print. The library lists every element of a chapter that has `role="doc-pagebreak"` or the
`epub:type` `pagebreak`, labelled by its `aria-label`, its `title` or its text:

```html
<span role="doc-pagebreak" epub:type="pagebreak" aria-label="42"></span>
```

An element without identifier gets one (`page-1`…). A marker without label is not listed. `Metadata::$source` names
the printed edition. UJO documents get their markers from their page breaks: see [UJO documents](ujo.md).

## EPUB 2 table of contents

Reading systems made for EPUB 2 — some older e-readers and library applications — do not read the navigation
document. `Book::withNcx()` adds `toc.ncx` for them, declared in the manifest and named by the `toc` attribute of the
spine:

```php
$book = $book->withNcx();
```

It holds the same entries as the table of contents, nested the same way, and the same page list: a page labelled with
digits is a `normal` page with its number, one labelled with a roman numeral a `front` page, any other a `special`
page. Entries that lead to the same address share their play order. Its unique identifier is the book's, so the two
tables describe one publication. `withNcx(false)` takes it out again.

## Labels

`NavigationLabels` holds the words of the navigation document, English by default; give them in the book's language:

```php
$book = $book->withLabels(new NavigationLabels(
    contents: 'Table des matières',
    landmarks: 'Repères',
    pages: 'Pages',
    cover: 'Couverture',
    tableOfContents: 'Table des matières',
    start: 'Début du texte',
));
```
