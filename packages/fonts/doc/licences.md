# Licences

## What a face says

`$font->licence()` gives a `Licence` with the description (name 13) and the URL (name 14) the font states, either of
which may be null. `spdx()` recognises the licences most fonts are published under:

| SPDX identifier | Recognised from |
|---|---|
| `OFL-1.1` | `openfontlicense.org`, `scripts.sil.org/OFL`, or "Open Font License" |
| `Apache-2.0` | `apache.org/licenses/LICENSE-2.0`, or "Apache License, Version 2.0" |
| `UFL-1.0` | `ubuntu.com/legal/terms-and-policies/font-licence`, or "Ubuntu Font Licence" |
| `MIT` | `opensource.org/licenses/MIT`, or "MIT License" |

The comparison ignores case, and is made on the URL and the description separately. It is a hint for a register or
an admin screen, not a legal reading: the licence that binds is the one under which the font was obtained, which a
commercial font often states only in a separate file.

## Licence records

A `LicenceRecord` is what an application keeps about one font file: its path, its SHA-256, its `Format`, and a
`FaceLicence` per face — family, subfamily, PostScript name, version, copyright, trademark, manufacturer, designer,
vendor URL, licence description and URL, SPDX identifier, embedding usage, and whether subsetting is allowed and the
font is bitmap only. `LicenceRecord::of(string $file, FontFile $font)` builds one; `licence()` gives the first face's,
which the faces of a collection usually share.

## The register

A `LicenceRegister` holds one record per file, sorted by path.

| Method | Effect |
|---|---|
| `LicenceRegister::scan(string $directory, ?Closure $brotli = null)` | Records every `.ttf`, `.otf`, `.ttc`, `.otc`, `.woff` and `.woff2` file under a directory, extension case aside, by its path relative to the directory with `/` between folders; a file that is not a font throws `InvalidFont` |
| `toJson()`, `LicenceRegister::fromJson(string $json)` | The register as pretty-printed JSON, and back; JSON that is not a register throws `InvalidLicenceRegister` |
| `with(LicenceRecord $record)` | A register with a record added, or put in place of the record of the same file |
| `get(string $file)`, `records()` | One record by path, or all of them |
| `changedFiles(LicenceRegister $other)` | The files present in only one of two registers, or with another SHA-256: the fonts whose licence must be checked again |

```json
{
    "fonts": [
        {
            "file": "example/ExampleSans-Regular.ttf",
            "sha256": "9f2c…",
            "format": "truetype",
            "faces": [
                {
                    "family": "Example Sans",
                    "subfamily": "Regular",
                    "postScriptName": "ExampleSans-Regular",
                    "version": "Version 2.000",
                    "copyright": "Copyright 2026 The Example Sans Project Authors",
                    "trademark": null,
                    "manufacturer": null,
                    "designer": null,
                    "vendorUrl": null,
                    "licence": "This Font Software is licensed under the SIL Open Font License, Version 1.1.",
                    "licenceUrl": "https://openfontlicense.org",
                    "spdx": "OFL-1.1",
                    "embedding": "installable",
                    "subsetting": true,
                    "bitmapOnly": false
                }
            ]
        }
    ]
}
```

Keep the register in version control next to the fonts. In continuous integration, scan the directory and compare
with the register kept: a file added or changed fails the build until someone has read its licence and committed the
new register.

```php
$kept = LicenceRegister::fromJson(file_get_contents('assets/fonts/licences.json'));
$changed = $kept->changedFiles(LicenceRegister::scan('assets/fonts'));

if ([] !== $changed) {
    fwrite(STDERR, "Fonts whose licence was not recorded:\n  ".implode("\n  ", $changed)."\n");
    exit(1);
}
```

The records hold what the fonts say. Add what they do not — where a font was bought, the number of the licence, the
domains it covers — in a file of the application's own, keyed by the same paths.
