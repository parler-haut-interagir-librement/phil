# `@font-face` rules

`FontFaceRule` writes the CSS that serves a face. It is immutable: each `with…()` method returns a new rule.

```php
use Phil\Fonts\Css\FontDisplay;
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\Format;

echo FontFaceRule::for($font)
    ->withUrl('/fonts/example.woff2', Format::Woff2)
    ->withUrl('/fonts/example.woff', Format::Woff)
    ->withDisplay(FontDisplay::Fallback)
    ->toCss();
```

```css
@font-face {
    font-family: "Example Sans";
    src: local("Example Sans Bold"), local("ExampleSans-Bold"), url("/fonts/example.woff2") format("woff2"), url("/fonts/example.woff") format("woff");
    font-weight: 700;
    font-style: normal;
    font-stretch: 100%;
    font-display: fallback;
}
```

## Building a rule

| Method | Effect |
|---|---|
| `FontFaceRule::for(Font $font)` | A rule under the face's family, with its full and PostScript names as `local()` sources — one when they are the same — and no file yet |
| `FontFaceRule::forFile(FontFile $file, string $url, int $face = 0)` | The same, for a face of a file served at a URL in the file's format |
| `withUrl(string $url, Format $format)` | A file to download, after the sources already given: list the smallest format first |
| `withFamily(string $family)` | Another family name, to group faces under one name or to name a face that has none |
| `withoutLocal()` | No `local()` source: the browser downloads the file even when a font of the same name is installed |
| `withDisplay(FontDisplay $display)` | `auto`, `block`, `swap` (the default), `fallback` or `optional` |
| `withUnicodeRange(?array $ranges = null)` | A `unicode-range` descriptor: the runs given, or those of the face's character map |

`toCss()` throws `InvalidFontFace` when the rule has no family — the font names none and none was given — or no source.

## Descriptors

| Descriptor | Written from |
|---|---|
| `font-weight` | The weight class, or the range of the `wght` axis of a variable font: `100 900` |
| `font-style` | `normal`, `italic` or `oblique`, from the face's style |
| `font-stretch` | The percentage of the width class, or the range of the `wdth` axis: `75% 125%` |
| `unicode-range` | Each run as `U+20-7E`, a single code point as `U+E9` |

Ranges are rounded to three decimals. The range of a `slnt` axis and the angle of an oblique face are not written:
add `font-style: oblique 0deg 12deg` to the application's own CSS when a variable font's slant must be selectable.

## Escaping

Font names and URLs come from files people upload. Every string is written between double quotes; `"` and `\` are
escaped with a backslash, and `<`, `>` and the control characters as CSS hexadecimal escapes. A name like
`</style><script>` therefore can end neither the string, nor the rule, nor the `<style>` element that holds it.

The URL is written as given: rewriting it to a path the application serves, or refusing a scheme, belongs to the
application, or to `phil/css-sanitizer` when the CSS is checked as a whole.
