# Changelog

All notable changes to `phil/footnotes-sidenotes` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `NoteResolver`: one walk over a normalized UJO (Universal JSON Output) document that collects the
  `<sup data-footnote>` references, in every text of every block however nested, and the `footnote` blocks, and numbers
  the notes in the order of their first reference; `Resolution`, `Note` and `NoteReference`.
- `Problem` and `ProblemKind`: a reference without a note, a note never referenced, a duplicate identifier, a reference
  inside a note, a note referenced several times.
- `NoteOptions`, `Restart` and `Numbering`: numbering again per document, per chapter or per page marker; decimal, lower
  and upper roman numerals, sequences of symbols; one numbering per locale or language.
- `Html\NotesRenderer` and `Placement`: the notes at the end of the document, at the end of each section, in the margin
  or at the foot of the printed page, through the caller's `HtmlRenderer`; identifiers under a prefix, safe with several
  documents on a page; references inside a link written without a link.
- Accessible output: a link and backlink pair for each reference, one backlink per reference of a note, a labelled
  region with `doc-endnotes`, `doc-noteref` and `doc-backlink`, the text of notes through the `InlineSanitizer` of
  `phil/ujo`, on one line whatever its source.
- `NoteLabels`: the title and the accessible names in English and French, or from the application.
- `Text\NotesPlainText`: references written `[1]` and notes after the text, for a search index or an accessibility check.
- `Stylesheet`, with `resources/css/notes.css`, margin notes on wide viewports and an inline toggle on narrow ones,
  without a script, in the PHIL UX classes and tokens, and `resources/css/notes-print.css`, in CSS GCPM: `float: footnote`,
  `::footnote-call`, `::footnote-marker`, the `@footnote` area; `resources/css/notes-print-per-page.css`, the footnote
  counter of the engine; `Stylesheet::contents()`, the text of a sheet.
- `Exception\FootnotesException` and `Exception\InvalidOptions`.
- The benchmark `NoteResolverBench` and the fuzz target `footnotes-sidenotes`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
