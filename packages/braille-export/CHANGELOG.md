# Changelog

All notable changes to `phil/braille-export` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- The document model: `Document` with its language and title, `Heading`, `Paragraph`, `ListBlock` and `ListItem`,
  `Table`, `PageBreak`, `SectionBreak` and `PrintPage`, each a `Contract\Block`.
- `UjoReader`: a UJO (Universal JSON Output) document of `phil/ujo` read as a document, headings, paragraphs, lists,
  tables and page breaks kept, every other block read as its plain text.
- The `Contract\Translator` port: a batch of texts to Unicode braille, by language and `Contraction`.
- `UncontractedTranslator`: uncontracted French, as the Code braille français uniformisé writes it, and English, as
  Unified English Braille writes it, in PHP: letters, accented letters, capitals and words in capitals, numbers, the
  common punctuation; letters folded to their base; a strict mode that refuses a character without braille.
- `ChainTranslator`: the first of several translators that supports the language and the contraction.
- `Braille`: the cells of dots, the check and the length of Unicode braille.
- `BrailleExporter` and `PageFormat`: braille pages of 40 cells by 25 lines by default, paragraphs, headings of three
  kinds kept with their next line, lists indented by level, tables linearized, words cut after their own hyphen or
  with one added, never after an announcing cell, braille page numbers, print page numbers and their lines, sections,
  volumes, duplex, a bound on the pages; one batch per document to the translator, its answer checked.
- `Book`, `Volume`, `Section` and `Page`: the laid-out book.
- `BrfWriter`: North American ASCII braille, eight-dot cells refused.
- `PefWriter` and `PefMetadata`: PEF 2008-1 with its volumes, sections, pages and rows and its Dublin Core metadata,
  an identifier derived from the content without one.
- `UnicodeWriter`: the pages as Unicode braille, for a preview.
- The exceptions, each a `BrailleExportException`: `UnsupportedTranslation`, `UntranslatableCharacter`,
  `InvalidBraille`, `DocumentTooLong`, `UnrepresentableCell`; `TranslationFailed` for the translators.
- The fuzz target of the layout, and the benchmark of an export.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
