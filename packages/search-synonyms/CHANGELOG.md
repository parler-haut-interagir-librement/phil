# Changelog

All notable changes to `phil/search-synonyms` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `SynonymSet`, an equivalent or one-way rule, its phrases normalized, kept once and checked against their limits;
  `SynonymList`, the rules of a language under its primary subtag; `InvalidSynonyms`, with the line of a file.
- `SynonymValidator` and `Violation`: a phrase on the left of two rules, a one-way rule leading to a phrase another
  rule rewrites.
- `SolrSynonymsReader` and `SolrSynonymsWriter`: the Solr synonyms format, comments, escapes, a byte order mark and
  every line break read, a line that is no rule refused with its number, a file of 1 MiB and lines of 4096 bytes at
  most, what is written read back as the same rules; `JsonSynonyms`, the JSON document.
- `SynonymStore`; `FileSynonymStore`, a file per language in the Solr format, read when asked and written atomically;
  `InMemorySynonymStore`.
- The fuzz targets of the two readers.
- The package: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
