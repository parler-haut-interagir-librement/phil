# Changelog

All notable changes to `phil/search-loupe` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `LoupeIndex`: `phil/search-bundle`'s `SearchIndex` on Loupe, a directory per index or memory, its fields learned
  as documents come and kept in `phil_search.json` with the directory of Loupe's files; the searched fields in the
  order they came, which Loupe ranks by; the file kept in memory, read again when it changed, and widened under a
  lock; a rebuild into a new directory, switched to once complete; on disk, a new field copies the documents into a
  new directory under the lock, as Loupe's own migration of its files fails past a hundred documents; writes under
  the shared lock, never into a directory being copied; index names checked; a query's list of values searched with
  `IN`; facets from Loupe's facet distribution, each facet whose filter is set counted by one more search without it.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
