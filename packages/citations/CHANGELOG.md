# Changelog

All notable changes to `phil/citations` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- The CSL-JSON model: `Reference`, the 45 item types of CSL 1.0.2, name and date variables, `Name` in its parts or
  literal, `PartialDate` and `CslDate` with ranges, seasons, approximation and literals; `CslJson` to read and write
  it, bounded in size, items and depth.
- `BibTeXReader`: entries in braces or parentheses, `@string` and month macros, `#` concatenation, `@comment` and
  `@preamble`, LaTeX accents and commands made plain Unicode, names in BibTeX's three forms, `crossref`, BibLaTeX
  dates; `RisReader`: records, types, tags, continued lines, names and made ids; `ImportLimits` and `InvalidImport`
  with the line of the problem.
- `ReferenceList`: duplicates by DOI, or by title, year and first author, cited as one.
- `Cite`, `Citation`, `Locator` and `CitationProcessor`: citations resolved, the bibliography ordered alphabetically
  in the collation of the language or by first citation, numbered, with year letters and first cites told apart, the
  uncited listed on request.
- `Contract\CitationStyle`, `Style\AbstractStyle` and three styles: `AuthorDate` in the manner of APA, `Notes` in the
  manner of Chicago, `Numeric` in the manner of IEEE.
- `Terms` for English and French, `RichText` as plain text or escaped HTML.
- The exceptions under `CitationsException`: `InvalidReference`, `InvalidImport`, `UnknownReference`, `MissingTerms`.
- Fuzz targets and their seeds for the BibTeX, RIS and CSL-JSON readers, and the unit tests of each part.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
