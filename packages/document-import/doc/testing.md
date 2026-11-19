# Testing

## The library's tests

The suite `tests/Unit` writes small DOCX and ODT archives, flat ODT, RTF and PDF files in memory (`tests/Unit/Fixture`)
and checks the UJO blocks, the media and the report of each construct: formatting and styles, headings and their levels,
lists and their nesting, tables and their spans, notes, links and fields, images in every form, SVG images sanitized,
what is reported, the metadata, the limits, the archives opened and written in memory or through a temporary file —
both ways on a PHP that has `ZipArchive::openString()` —, and each refusal with its message. The PDF parts —
parser, filters and their predictors, CMaps, glyph names, code pages — have their own tests, and every block an export
writes is read back by the importer. Markdown and HTML go through the readers of `phil/markup`.

From the monorepo root:

```sh
vendor/bin/phpunit packages/document-import/tests
```

The library is held to 100 % covered mutation score by Infection.

Each reader is also a fuzz target — `docx`, `odt`, `fodt`, `rtf`, `pdf` — that runs on files nobody wrote, from the
seeds of `tests/Fuzz/corpus/<target>`, and checks that the reader gives a document or refuses the file with an
exception of the library, nothing else:

```sh
composer fuzz -- docx odt fodt rtf pdf --budget 60
```

The readers were also run on documents written by real applications: DOCX and ODT written by Pandoc from Markdown,
and by macOS `textutil` from HTML, read back with the structure they were written with.

## Testing code that imports documents

Build the document in the test, or keep small files written by the word processor the application expects among the
test's fixtures, and check the blocks rather than the HTML:

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\Ujo\Block;

$imported = new DocumentImporter()->importFile(__DIR__.'/fixtures/manuscript.docx');

self::assertSame(['header', 'paragraph', 'list'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
self::assertTrue($imported->report->isEmpty());
```

A `MediaStore` written for the test records the images instead of writing them.
