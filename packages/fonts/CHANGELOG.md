# Changelog

All notable changes to `phil/fonts` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Font`: the metadata of a face — family and subfamily, typographic first; full, PostScript and version names;
  copyright, trademark, manufacturer, designer and their URLs; weight and width classes, `FontStyle` from `fsSelection`
  or `macStyle`, italic angle, fixed pitch, `Outlines`, variation axes (`Axis`) with weight and stretch ranges, and the
  runs of code points its character map declares.
- `Name\Names`, `Name\NameRecord` and `Name\NameId`: every string of the `name` table, UTF-16 and Mac Roman decoded,
  and the one a reader expects chosen — Windows US English, any Windows language, Unicode, then Macintosh.
- `EmbeddingPermissions` and `EmbeddingUsage`: the `OS/2` `fsType` read as installable, editable, preview and print or
  restricted, the least restrictive winning when several bits are set; no subsetting, bitmap only, and whether the
  outlines may be embedded.
- `Licence\Licence`: the licence description and URL of the `name` table, and the SPDX identifier of the OFL, Apache
  2.0, Ubuntu Font Licence and MIT recognised from them.
- `FontFile`: a font file read from bytes or from a path into its `Format`, its faces, its SHA-256 and the extended
  metadata of WOFF and WOFF2; TrueType, OpenType, TrueType and OpenType collections, WOFF tables inflated with zlib,
  WOFF2 tables through a Brotli decompressor the application passes (the transformed `glyf`, `loca` and `hmtx` tables
  are not rebuilt; WOFF2 collections are refused); the work a file causes bounded — 32 MiB, 256 tables a face each
  listed once, 64 faces, 64 MiB of tables copied or decompressed, paid for before each copy or decompression, no
  overlapping WOFF blocks — and only the tables a face is read from copied or decompressed.
- `Brotli::decompressor()`: `brotli_uncompress()` when `ext-brotli` is loaded, which Composer suggests, and null
  otherwise; a WOFF2 file read without a decompressor throws `UnsupportedFont::woff2WithoutBrotli()`, whose message says
  to install `ext-brotli` or to read the WOFF, TrueType or OpenType file of the font.
- `Licence\LicenceRecord`, `Licence\FaceLicence` and `Licence\LicenceRegister`: one licence record per file with its
  SHA-256 and what each face says, a register scanned from a directory, written to and read back from JSON, and the
  files added, removed or changed between two registers.
- `Css\FontFaceRule` and `Css\FontDisplay`: `@font-face` rules with `local()` and `url()` sources and their formats,
  weight and stretch ranges for variable fonts, `font-style`, `font-display` and `unicode-range`; every string escaped
  so that nothing taken from a font ends the rule or its `<style>` element.
- `FontsException`, implemented by `InvalidFont`, `UnsupportedFont`, `InvalidFontFace` and `InvalidLicenceRegister`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
