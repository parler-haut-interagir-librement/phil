# Extension points

What an application implements, replaces or relies on.

## `MediaStore`

`Phil\DocumentImport\MediaStore` — where the images of an imported document go. See [Media](media.md).

| Method | Contract |
|---|---|
| `store(Media $media): string` | keeps the image and answers the address its image block shows: `http` or `https` with a host, or relative and not protocol-relative |

An SVG image reaches the store already sanitized.

## `Contract\MediaSource`

`Phil\DocumentImport\Contract\MediaSource` — where the exporter finds the images a document shows. See
[Exporting](export.md).

| Method | Contract |
|---|---|
| `fetch(string $url): ?string` | the bytes of the image at an address, or `null` when the application does not hold it; never a request to the address |

`MediaMap` is the source over images in memory: the `Media` of an import, or bytes, by address.

## The style map

`StyleMap`, given to the `DocumentImporter`, names what the application's paragraph styles become: see
[Style map](styles.md).

## Readers and normalizer

The Markdown and HTML readers are any `Phil\Markup\Reader\MarkupReader`, and the normalizer of DOCX and ODT
documents any `Phil\Ujo\Normalizer`, given to the `DocumentImporter`: see [Markdown and HTML](markup.md).

## The SVG sanitizer

The `Phil\SvgSanitizer\SvgSanitizer` given to the `DocumentImporter` decides what an SVG image keeps, through its
policy: see [Media](media.md).

## Limits

`Limits`, given to the `DocumentImporter`, bounds what a document may hold — entries, files and streams once
decoded, nesting, PDF objects: see [Importing](importing.md).

## The report as a block

`Report::toBlock()` and `Imported::documentWithReport()` take the title of the block: the application gives it in its
reader's language, and translates the sentences by the values of `Omitted` when it renders them itself.
