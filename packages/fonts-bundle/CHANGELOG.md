# Changelog

All notable changes to `phil/fonts-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `FontFiles`: font files read with the Brotli decompressor PHP has — WOFF2 when `ext-brotli` is loaded, which
  Composer suggests, every other format everywhere —, and `FontUnreadable`, which names the file and, for a WOFF2 file
  without `ext-brotli`, says to install it or to use the WOFF, TrueType or OpenType file of the font.
- `FontCatalogue` and `CatalogueEntry`: the font files of a directory and its folders, read once, sorted by path, with
  the reason of each file that cannot be read; a file by path, a face by family, weight — within a variable font's
  range — and style among formats in order, the face itself, and `FontNotFound`; the licence records of the files, a file that cannot
  be read keeping the record already kept.
- `FontFaces`: one `@font-face` rule per face, its files in several formats listed WOFF2, WOFF, OpenType, TrueType,
  with URLs under the public path or of the application's own; collections, nameless faces and faces none of whose
  files was read left out.
- `Licences`: the licence register kept as JSON beside the fonts, compared with the catalogue, and written once the
  files are checked.
- `PhilFontsBundle` and the `phil_fonts` configuration: `directory`, `public_path`, `font_display` and
  `licence_register`.
- The commands `phil:fonts:list`, `phil:fonts:licences` (failing while a file's licence is unchecked, `--write`) and
  `phil:fonts:css` (`--output`), and the Twig function `fonts_css()`.
- `PdfFonts`, the TrueType faces of the catalogue for the native PDF writer of `phil/paged-media`, and `MediaFonts`,
  the fonts uploaded to `phil/media-bundle`, each registered when its package is installed; `RequiredServicesPass`,
  which removes `MediaFonts` when the media library is not enabled.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
