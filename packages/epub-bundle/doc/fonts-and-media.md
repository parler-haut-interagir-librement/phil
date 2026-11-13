# Fonts and media

## The fonts of `phil/fonts-bundle`

```php
$book = $epubFonts->embed($book, 'Example Serif', 'Example Sans');
```

`embed(Book $book, string ...$families)` adds, for each face of each family in the font directory — case aside —, one
file and one `@font-face` rule:

- The file is the smallest of the face's formats, WOFF2, WOFF, OpenType then TrueType: reading systems read WOFF2 even
  where PHP cannot, so a `.woff2` file is embedded beside a `.ttf` sibling read without `ext-brotli`. It goes under
  `fonts.directory`, its path made of letters, digits, `.`, `_`, `-` and `/` only, and is obfuscated when
  `fonts.obfuscate` is set.
- The face's metadata comes from the first of its files that was read; a family none of whose files was read throws
  `FontNotEmbeddable`.
- A face whose licence forbids embedding (`OS/2` restricted), or allows bitmaps only, throws `FontNotEmbeddable` before
  the book is written. Obfuscation does not make a restricted font embeddable.
- The rules go in one stylesheet at `fonts.stylesheet`, which the library links from every content document, with URLs
  relative to it and no `local()` source, so the reading system uses the embedded file.

Collections are left out. Call `embed()` once per book: a second call adds a second stylesheet at the same path, which
the library refuses.

## The media of `phil/media-bundle`

| Method | Returns |
|---|---|
| `image(Media $media, ?string $path)` | An image asset of the medium's file, at `images/<id>-<file name>` by default |
| `cover(Media $media, ?string $path)` | A `Cover` of that image with the medium's alternative text; a medium without one throws the library's `InvalidBook` |
| `font(Media $media, bool $obfuscate, ?string $path)` | A font asset, at `fonts/<id>-<file name>` by default |

The library checks the media type of each file against the EPUB core media types.
