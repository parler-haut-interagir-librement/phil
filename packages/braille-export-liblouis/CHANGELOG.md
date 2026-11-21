# Changelog

All notable changes to `phil/braille-export-liblouis` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `LiblouisTranslator`: the `Translator` of `phil/braille-export` on `lou_translate`, a whole batch in one run, one
  text per line of its standard input, its answer read as Unicode braille by the `unicode.dis` display table and
  checked, long texts sent in pieces, the arguments given as a list and never to a shell, a timeout, limits on the
  input and on the output, a table path, the version read, `isAvailable()` for a deployment check.
- `LiblouisTables`: the table of each language and contraction, Unified English Braille and the Code braille français
  uniformisé by default, a region falling back to its language, names checked.
- `LiblouisException` and its exceptions: `ExecutableNotFound`, `TableNotFound`, `LiblouisFailed`, each with the exit
  code and the bounded error output of the program.
- The integration suite against the real program, skipped without liblouis.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
