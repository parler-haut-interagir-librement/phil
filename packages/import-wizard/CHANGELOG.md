# Changelog

All notable changes to `phil/import-wizard` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Added

- `doc/question-files.md`: the conventions the CSV files of the questions of quizzes and surveys share — one row per
  question by `question_id`, its type, lists separated by `|`, choices by position or `value = label`, the check of each
  row in the dry run, the bounds —, and the profiles of the packages that own them.

## 0.1.0-alpha - 2026-11-24

### Added

- `ImportProfile`, `Column`, `ColumnType` and `Reference`: the columns of an importable type — text, integer, decimal
  kept as text, yes or no in English and French, date `YYYY-MM-DD`, email in lower case —, required, length, choices
  and references, the natural key of one or more required columns, the rules across a row; serialised to an array or
  JSON and read back, refusing data of the wrong shape by field, JSON within 1 MiB and 16 levels.
- `ColumnMapping`: the mapping of the headers, guessed from each column's name, header and aliases, folded for case,
  accents and punctuation, adjusted header by header and kept in the profile to replay on the next file.
- `CsvRowSource`, `CsvReader`, `CsvDialect` and `SourceLimits`: CSV read as RFC 4180 writes it, the delimiter detected,
  UTF-8 or Windows-1252 or ISO-8859-1, the byte order mark dropped, each record with its physical line; 20 MiB, 100,000
  rows and 256 cells a row by default, a file beyond them or with a quote left open refused whole.
- `ImportPlanner` and `ImportPlan`: the dry run — create, update or refuse, row by row, by natural key —, every refused
  cell with its `Reason`, duplicate keys, references resolved in the file, above or below, or through the lookup in
  chunks of 500, cycles and references to refused rows refused, rows ordered parents first; `preview()` in the order of
  the file and a fingerprint of the plan.
- `Importer`, `Batch` and `ImportResult`: the plan written through the target in batches of 500 by default, never a row
  and the row it refers to in one batch, a row whose record of the file was refused never written, a plan that differs
  from the one previewed refused with `PlanChanged`.
- `RejectedRowsFile`, `TemplateFile` and `CsvWriter`: the refused rows as a CSV file with their line and errors, to
  correct and import again; the header-only template of a profile; cells a spreadsheet would run as formulas written
  after an apostrophe, dropped when read back.
- `LmsProfiles`: the CSV formats of the people, of their enrolments in courses and of the tree of organisational
  units, with their English and French headers.
- `DateOrder` and the ports `ImportTarget`, `KeyLookup`, `RowSource` and `RowRule`.
- The fuzz target of the CSV reader and the dry run, and the benchmark of reading, planning and writing.
- The documentation in `doc/`: installation, profiles, formats, the dry run, extension points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
