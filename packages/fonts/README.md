# phil/fonts

**What a font file says about itself — its names, its weight, its embedding licence — and the `@font-face` rule to serve it.**

phil/fonts reads the metadata of font files: TrueType and OpenType, TrueType and OpenType collections, WOFF and WOFF2.
For each face it gives the family and subfamily (typographic first, as CSS expects), the full and PostScript names,
the version, copyright, trademark, maker and designer, the licence description and URL from the `name` table, the
weight, width and slope, the embedding permissions of the `OS/2` table — installable, editable, preview and print,
restricted, no subsetting, bitmap only —, the variation axes of a variable font and the runs of characters its
character map covers. From that it writes `@font-face` rules, and keeps a licence record per file in a register that
says which files changed since their licence was last checked.

It is for any PHP application that serves or embeds fonts it did not draw: a site that lets its editors upload fonts
and must write their CSS, a PDF or EPUB pipeline that must know whether a font may be embedded and subset, an admin
screen that lists each font with its licence, a build step that refuses a font whose licence it does not know.
`phil/paged-media` embeds TrueType fonts in PDF documents; this library is how an application checks them first.

It is a plain PHP library that follows the OpenType 1.9.1, WOFF 1.0 and WOFF 2.0 specifications and whose
only requirements are `ext-mbstring` and `ext-zlib`. WOFF2 compresses its tables with Brotli, which PHP does not
decode on its own: a WOFF2 file is read through a decompressor the application passes, `Brotli::decompressor()`, which
is `brotli_uncompress()` when `ext-brotli` is loaded; without it, WOFF, TrueType and OpenType files are read and a WOFF2
file is refused with a message that says so. Nothing is installed with it, and nothing is downloaded.

The design is small: `FontFile` reads a file into its `Format` and its `Font` faces; a `Font` holds `Names`,
`EmbeddingPermissions`, `Axis` values and character runs; `FontFaceRule` writes CSS from a `Font`; `LicenceRecord`
and `LicenceRegister` keep what each file says about its licence. phil/fonts and phil/paged-media do not depend on
each other: see [Embedding permissions](doc/embedding.md) for why, and in which direction they would.

## Status

| Part | State |
|---|---|
| Containers: TrueType, OpenType, collections, WOFF (zlib), WOFF2 (Brotli through a decompressor given) | Implemented and tested |
| `name` table: every record, UTF-16 and Mac Roman decoded, the name a reader expects chosen | Implemented and tested |
| Weight, width class, slope, italic angle, fixed pitch, outlines, variation axes, character runs | Implemented and tested |
| `OS/2` embedding permissions, the least restrictive usage when several are set | Implemented and tested |
| Licence description and URL, and the SPDX identifier of the common font licences recognised | Implemented and tested |
| `@font-face` rules: local and remote sources, ranges for variable fonts, `font-display`, `unicode-range`, escaping | Implemented and tested |
| Licence records per file, a register scanned from a directory, kept as JSON, compared with a new scan | Implemented and tested |
| WOFF2 collections, WOFF2 decoding without a Brotli decompressor | Not supported: see [Reading font files](doc/reading.md) |
| Rebuilding the WOFF2-transformed `glyf`, `loca` and `hmtx` tables | Not needed: they carry no metadata |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and `ext-brotli` for WOFF2. |
| [Reading font files](doc/reading.md) | `FontFile`, the formats, faces, names and the other metadata of a `Font`. |
| [Embedding permissions](doc/embedding.md) | `EmbeddingPermissions` and `EmbeddingUsage`, and the relation to `phil/paged-media`. |
| [`@font-face` rules](doc/font-face.md) | `FontFaceRule`: sources, descriptors, variable fonts, `unicode-range`, escaping. |
| [Licences](doc/licences.md) | `Licence`, `LicenceRecord` and `LicenceRegister`: what is kept, the JSON, finding changed files. |
| [Extension points](doc/extension-points.md) | The Brotli decoder, the rules and the register: what the application chooses. |
| [Exceptions](doc/exceptions.md) | `FontsException` and its four exceptions. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that reads fonts. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/fonts
```

```php
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;

$file = FontFile::fromFile('/path/to/ExampleSans-Bold.ttf');
$font = $file->face();

echo $font->family();                          // Example Sans
echo $font->weight;                            // 700
echo $font->embedding->usage->value;           // installable
echo $font->licence()->spdx();                 // OFL-1.1

echo FontFaceRule::for($font)
    ->withUrl('/fonts/example-sans-bold.woff2', Format::Woff2)
    ->withUrl('/fonts/example-sans-bold.ttf', Format::TrueType)
    ->toCss();
```

```php
use Phil\Fonts\Brotli;
use Phil\Fonts\Licence\LicenceRegister;

$kept = LicenceRegister::fromJson(file_get_contents('fonts/licences.json'));
$now = LicenceRegister::scan('fonts', Brotli::decompressor());

foreach ($kept->changedFiles($now) as $file) {
    echo "Check the licence of {$file}\n";
}

file_put_contents('fonts/licences.json', $now->toJson());
```

## Requirements

PHP 8.5 or later, `ext-mbstring` and `ext-zlib`; `ext-brotli`, or another Brotli decoder, for WOFF2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
