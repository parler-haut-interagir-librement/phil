# `@font-face` rules

`FontFaces` writes the rules of the catalogue:

```php
$css = $faces->css();
```

The files of one face in several formats — the same path but for the extension, such as `sans/Regular.woff2`,
`sans/Regular.woff` and `sans/Regular.ttf` — make one rule. Its sources are the face's local names, then its files
smallest format first: WOFF2, WOFF, OpenType, TrueType. The face's metadata — family, weight or weight range, style,
stretch — comes from the first of those files that was read, so a WOFF2 file that cannot be read without `ext-brotli`
is still listed first beside a sibling that was read.

```css
@font-face {
    font-family: "Example Sans";
    src: local("Example Sans Regular"), local("ExampleSans-Regular"), url("/fonts/sans/Regular.woff2") format("woff2"), url("/fonts/sans/Regular.ttf") format("truetype");
    font-weight: 400;
    font-style: normal;
    font-stretch: 100%;
    font-display: swap;
}
```

Left out: collections, which browsers do not load through `@font-face`; faces whose `name` table gives no family; and
faces none of whose files was read. Every string taken from a font is escaped by `phil/fonts`, so nothing ends the rule
or the `<style>` element.

| Method | Returns |
|---|---|
| `rules(?Closure $url = null)` | The `FontFaceRule` values, by stem |
| `css(?Closure $url = null)` | The rules as one stylesheet, a blank line between two, ending with a line break; empty without fonts |
| `url(CatalogueEntry $entry)` | The URL of a file: the public path, then its path with each folder and the name percent-encoded |

The closure gives the URL of a file in place of `url()`, for a stylesheet that lives elsewhere — the EPUB of
`phil/epub-bundle` points to `../fonts/…` this way:

```php
$faces->css(static fn (CatalogueEntry $entry): string => 'https://cdn.example.com/fonts/'.$entry->path);
```

## In a template

```twig
<style>{{ fonts_css() }}</style>
```

`fonts_css()` is registered when Twig is installed, and prints `css()` as it is.

## In a file

```sh
bin/console phil:fonts:css --output=assets/styles/fonts.css
```

Without `--output`, the command prints the rules. A build step that writes the stylesheet once spares the request
from reading the fonts; the catalogue is read at most once per request otherwise.
