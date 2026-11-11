# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Fonts\FontFile` | `final readonly class` | A font file: its format, its faces, its SHA-256 and its extended metadata |
| `Phil\Fonts\Brotli` | `final class` | The Brotli decompressor of `ext-brotli` for WOFF2, or none without the extension |
| `Phil\Fonts\Format` | enum | TrueType, OpenType, collection, WOFF or WOFF2, each the keyword of CSS `format()` |
| `Phil\Fonts\Font` | `final readonly class` | The metadata of one face |
| `Phil\Fonts\FontStyle` | enum | Normal, italic or oblique |
| `Phil\Fonts\Outlines` | enum | TrueType, PostScript or none |
| `Phil\Fonts\Axis` | `final readonly class` | A variation axis: tag, minimum, default, maximum, name identifier |
| `Phil\Fonts\EmbeddingPermissions` | `final readonly class` | The `OS/2` `fsType`: usage, subsetting, bitmaps |
| `Phil\Fonts\EmbeddingUsage` | enum | Installable, editable, preview and print, restricted |
| `Phil\Fonts\Name\Names` | `final readonly class` | Every string of the `name` table, and the one to show |
| `Phil\Fonts\Name\NameRecord` | `final readonly class` | One string with its platform, encoding, language and name identifier |
| `Phil\Fonts\Name\NameId` | enum | The predefined name identifiers, 0 to 25 |
| `Phil\Fonts\Css\FontFaceRule` | `final readonly class` | An `@font-face` rule written from a face |
| `Phil\Fonts\Css\FontDisplay` | enum | The values of `font-display` |
| `Phil\Fonts\Licence\Licence` | `final readonly class` | The licence description and URL of a face, and the SPDX identifier recognised |
| `Phil\Fonts\Licence\FaceLicence` | `final readonly class` | What one face says about its licence, as kept in a record |
| `Phil\Fonts\Licence\LicenceRecord` | `final readonly class` | The licence record of one file |
| `Phil\Fonts\Licence\LicenceRegister` | `final readonly class` | One record per file: scanned, kept as JSON, compared |
| `Phil\Fonts\Licence\Field` | `final readonly class`, internal | The typed fields of a record read back from JSON |
| `Phil\Fonts\Reader\FontReader` | `final readonly class`, internal | The containers, down to the tables of each face |
| `Phil\Fonts\Reader\FaceReader` | `final readonly class`, internal | A `Font` from the tables of a face |
| `Phil\Fonts\Reader\NameTable` | `final readonly class`, internal | The strings of a `name` table decoded |
| `Phil\Fonts\Reader\CharacterMap` | `final readonly class`, internal | The runs of code points of a `cmap` table |
| `Phil\Fonts\Reader\Binary` | `final readonly class`, internal | Big-endian numbers read with bounds checked |
| `Phil\Fonts\Exception\FontsException` | interface | Every exception of the library |
| `Phil\Fonts\Exception\InvalidFont` | `final class` | A file that is not a font the library can read |
| `Phil\Fonts\Exception\UnsupportedFont` | `final class` | A valid font the library does not read |
| `Phil\Fonts\Exception\InvalidFontFace` | `final class` | An `@font-face` rule that cannot be written |
| `Phil\Fonts\Exception\InvalidLicenceRegister` | `final class` | A register that cannot be read back |
