# Changelog

All notable changes to `phil/print-preflight-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PrintProfiles`: the printer profiles of the configuration by name, a default one, and `UnknownProfile`.
- `Lengths`: lengths (`3mm`, `0.125in`, `9pt`, `12px`, `0`) and sizes (`a4`, `a5`, `letter`, `148x210mm`) read from
  text.
- `Preflighter` and `#[AsPreflightCheck]`: the preflight of a document against a profile, with the checks of the
  application after the built-in ones.
- `MeasuredDocument` and the `FontResolver` port: a document measured on a page laid out for print, read from JSON in
  CSS pixels or another unit — pages, bleed, images with their natural and scaled sizes, boxes, colour spaces, ink
  coverage, overprint, line width and transparency when the script knows them, fonts by name and `fsType` or by
  family, weight and style —, and `InvalidMeasurement`, which names the field it cannot read.
- `PhilPrintPreflightBundle` and the `phil_print_preflight` configuration: `default_profile` and `profiles`, each with
  every rule of the library's profile — ink coverage, line width, overprint and transparency among them — and checked
  by the library when the container is built.
- The command `phil:print-preflight:check`, findings as text or JSON (`--format`), against a profile (`--profile`),
  failing on an error.
- `PdfLayout`, which records what the native PDF writer of `phil/paged-media` places — images, rectangles, lines of
  text and their fonts —; `MediaImages`, the images of `phil/media-bundle` placed on a page; and
  `CatalogueFontResolver`, the fonts of the catalogue of `phil/fonts-bundle`, removed by `FontCataloguePass` when that
  bundle is not enabled.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
