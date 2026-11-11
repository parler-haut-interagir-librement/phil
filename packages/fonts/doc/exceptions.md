# Exceptions

Every exception implements `Phil\Fonts\Exception\FontsException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidFont` | `InvalidArgumentException` | A file that cannot be read, shorter than 12 bytes, with an unknown signature, truncated, with a table outside the file, without a `name` table; a collection without a face or with a face that is not an `sfnt`; a WOFF block that does not inflate to its length; a Brotli block of the wrong length; an invalid WOFF2 number; a file past a limit (32 MiB, 256 tables a face, a tag listed twice, 64 faces, 64 MiB of tables, overlapping WOFF blocks); a face index the file does not have |
| `UnsupportedFont` | `RuntimeException` | A valid font the library does not read: a WOFF2 file without a Brotli decompressor (`woff2WithoutBrotli()`, whose message says to install `ext-brotli` or to read the WOFF, TrueType or OpenType file), a WOFF2 collection |
| `InvalidFontFace` | `InvalidArgumentException` | An `@font-face` rule without a family or without a source |
| `InvalidLicenceRegister` | `InvalidArgumentException` | A licence register that is not valid JSON, or a record with a field missing or of the wrong type, an unknown format or embedding usage, or no face |

Each message names what is wrong, for example `The font has no "name" table.` or `The field "sha256" must be a string.`
