# Reading font files

## Files and formats

`FontFile::fromBytes(string $bytes, ?Closure $brotli = null)` and `FontFile::fromFile(string $path, ?Closure $brotli = null)`
read a file into:

| Property | What it holds |
|---|---|
| `format` | The container, a `Format` |
| `faces` | The faces of the file, never empty: one, or several for a collection |
| `sha256` | The SHA-256 of the file's bytes, in hexadecimal |
| `extendedMetadata` | The extended metadata XML of a WOFF or WOFF2 file, decompressed, or null |

`face(int $index = 0)` gives one face, and throws `InvalidFont` for an index the file does not have.

| `Format` | Signature | Notes |
|---|---|---|
| `TrueType` | `00 01 00 00` or `true` | TrueType outlines, or none |
| `OpenType` | `OTTO` | PostScript (CFF) outlines |
| `Collection` | `ttcf` | Every face read, each with its own tables |
| `Woff` | `wOFF` | Each table inflated with zlib to the length it declares, or kept when stored as it is |
| `Woff2` | `wOF2` | Every table decompressed together through the Brotli decompressor given |

Each value of `Format` is the keyword of the CSS `format()` function. The library reads what it needs from the tables
and checks every read against the end of its table: a truncated file, a table outside the file, a WOFF block that does
not inflate to its declared length or a Brotli block of the wrong length throws `InvalidFont`.

Font files are often uploaded, so the work one can cause is bounded, and a file past a limit throws `InvalidFont`:

| Limit | Value |
|---|---|
| The file | 32 MiB |
| The tables a face lists | 256, each tag once |
| The faces of a collection | 64 |
| The tables and metadata, copied or decompressed | 64 MiB in all, paid for before each copy or decompression |

Only the six tables a face is read from are copied or decompressed; the others are checked to be within the file. Two
WOFF blocks that share bytes are refused.

WOFF2 encoders transform the `glyf`, `loca` and `hmtx` tables. They carry no metadata, so the library does not rebuild
them; it only notes that they are there. A WOFF2 collection throws `UnsupportedFont`, and so does a WOFF2 file read
without a decompressor: pass `Brotli::decompressor()`, which is `brotli_uncompress()` when `ext-brotli` is loaded (see
[Installation](installation.md#brotli-for-woff2)).

## Faces

A `Font` is read from six tables. Only `name` is required; each other table missing leaves its values at their
defaults.

| Table | What the library reads |
|---|---|
| `name` | Every string, decoded |
| `OS/2` | `usWeightClass`, `usWidthClass`, `fsType`, `fsSelection` |
| `head` | `macStyle`, when the face has no `OS/2` table |
| `post` | `italicAngle`, `isFixedPitch` |
| `fvar` | The variation axes |
| `cmap` | The Unicode subtable, format 12 or format 4 |

| Property or method | What it gives |
|---|---|
| `family()`, `subfamily()` | The typographic family and subfamily (names 16 and 17) when the font gives them, else names 1 and 2; an empty string when there is none |
| `fullName()`, `postScriptName()`, `version()` | Names 4, 6 and 5 |
| `copyright()`, `trademark()`, `manufacturer()`, `designer()`, `vendorUrl()`, `designerUrl()` | Names 0, 7, 8, 9, 11 and 12 |
| `licence()` | Names 13 and 14, as a `Licence` |
| `weight` | `usWeightClass`, from 1 to 1000; 400 when out of range; without `OS/2`, 700 for a bold `macStyle`, else 400 |
| `widthClass` | `usWidthClass`, from 1 to 9; 5 when out of range or missing |
| `style` | `Oblique` for the `fsSelection` oblique bit, `Italic` for its italic bit, else `Normal`; without `OS/2`, the italic bit of `macStyle` |
| `embedding` | The `EmbeddingPermissions` of `fsType`; see [Embedding permissions](embedding.md) |
| `outlines` | `TrueType` for a `glyf` table, `PostScript` for `CFF ` or `CFF2`, else `None` |
| `italicAngle`, `fixedPitch` | From `post`: degrees counter-clockwise from vertical, and whether every glyph has the same advance |
| `axes`, `axis($tag)`, `isVariable()` | The `fvar` axes, each an `Axis` with its tag, minimum, default, maximum and name identifier |
| `weightRange()` | The `wght` axis range, rounded, or the weight twice |
| `stretchRange()` | The `wdth` axis range, or the percentage the width class stands for (50 % to 200 %), twice |
| `unicodeRanges` | The runs of code points the character map declares, sorted and merged where they touch |

The runs come from the Unicode subtable as declared: format 12 when there is one, else format 4, whose closing segment
is left out. A code point a subtable maps to the missing glyph still counts as covered.

## Names

`$font->names` holds every string of the `name` table as `NameRecord` values — platform, encoding, language, name
identifier and the string in UTF-8. Strings for the Unicode platform and for the Windows platform in its Unicode and
symbol encodings are read as UTF-16; Macintosh strings in the Roman encoding are read as Mac Roman. Strings in other
encodings — the Macintosh Japanese or Chinese encodings, the deprecated ISO platform — are left out.

`get(NameId $id)` gives the string a reader is most likely to expect: Windows US English, then any other Windows
language, then Unicode, then Macintosh, an empty string counting as none. `all(NameId $id)` gives every string of a
name, to show a font's names in each language it was written for:

```php
use Phil\Fonts\Name\NameId;

foreach ($font->names->all(NameId::FullName) as $record) {
    printf("%d/%d/0x%04X: %s\n", $record->platform, $record->encoding, $record->language, $record->value);
}
```

The language of a record is the platform's numeric identifier: 0x0409 is US English and 0x040C French on Windows.
