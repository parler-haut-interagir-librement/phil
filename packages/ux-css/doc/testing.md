# Testing

| Area | What is covered |
|---|---|
| `Oklch` | The ways CSS writes it, what it refuses, the conversion to linear sRGB, the gamut and its tolerance, the luminance clipped, the contrast both ways |
| `TokenSheet` | The later declaration winning, comments, files, the tones and the roles found, `var()` with fallbacks, `light-dark()`, whatever their case and lines, a function that is not the whole value, nesting counted towards the limit, loops and dangling names |
| `ContrastRule` | The pairs listed, a pair below its minimum in each scheme, a token that does not resolve, the palette's 40 and 50 gaps, the gamut |
| `StylesheetBundler` | Each layered import inlined, the others left, a missing file, an import outside the directory of the entry, `dist/phil-ux.css` up to date |
| `Calc` | Numbers, lengths, durations, `calc()` and its operators, precedence and parentheses, `var()` followed and computed once, what has no value, the bounds on the text, its pieces and the depth of `var()` |
| `DesignTokens` | Each type of the format, aliases, `light-dark()` and its dark value, a zero among lengths, what is left out, the prefix, the order, names of digits as objects, rounding, phil-ux's tokens, `dist/tokens.json` up to date |
| The theme | The contrast rule on the package's tokens, the numbering of the palette, the grid of each role in both schemes, colours and dimensions in separate layers, the t-shirt scales and their multipliers, the touch targets |
| The stylesheets | The entry's layers and imports, every `var()` declared or with a fallback, every class named `isX` or `hasX` and documented, an animation only where reduced motion is handled, the elements and Symfony's form markup dressed, the components the kit relies on, no colour of their own nor palette read in the layers, logical directions, the print fallback, the tones and sizes last |

```sh
vendor/bin/phpunit packages/ux-css/tests
php packages/ux-css/bin/build.php    # after a change under css/
```

Two fuzz targets read inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/<target>`: `calc`, a finite quantity
or none, and `design-tokens`, every property a token or left out and the file encoded as JSON:

```sh
composer fuzz -- calc design-tokens
```
