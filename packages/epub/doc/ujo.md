# UJO documents

UJO (Universal JSON Output) is a JSON format for documents made of blocks; `phil/ujo` reads, checks and renders it. With
`phil/ujo` installed, `Ujo\UjoChapters` turns a UJO document into chapters:

```php
use Phil\Epub\Ujo\UjoChapters;

$chapters = new UjoChapters()->chapters($json); // or a Phil\Ujo\Document
$book = $book->withChapter(...$chapters);
```

The document is checked and rendered whole by `phil/ujo`'s purified HTML renderer — so that its `toc` block lists
every heading and its footnotes find their references —, then split at its top-level `header` blocks of level
`splitLevel` or higher (1 by default) with `HtmlSplitter`. Links of its `toc` block and footnotes that now point into
another chapter are rewritten by the writer.

## Page breaks

The start of the document is page `firstPage` (1 by default), and each `pageBreak` block starts the next page: each
becomes a page break marker, listed in the book's page list. A marker that would end a chapter — a page break just
before a heading — opens the next chapter instead. With `firstPage: null`, page breaks leave nothing.

```php
new UjoChapters(splitLevel: 2, firstPage: 5);
```

## Normalizer and renderer

`UjoChapters` takes the `Normalizer` and the `HtmlRenderer` of the application, to read its own block types or render
them its way; it replaces only the renderer of `pageBreak`. Images of UJO documents usually point to the application's
media addresses: add the images as assets and map their addresses with `Book::withUrls()`, or they are replaced by
their captions. Embeds, which load a remote page, are left out.
