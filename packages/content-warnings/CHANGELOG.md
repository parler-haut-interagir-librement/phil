# Changelog

All notable changes to `phil/content-warnings` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Catalogue`, `Category`, `Theme` and `LocalizedText`: themes by stable key, grouped in categories, labelled and
  described by locale, a label read in its locale, its language, the fallbacks, then the first one given; a
  catalogue of 200 categories and 2,000 themes at most.
- `CatalogueLoader`, which builds a catalogue from a definition or applies one onto a catalogue — categories and themes
  added or merged by locale, a theme moved to another category, themes and categories removed, or everything
  replaced — from an array, a PHP file or a JSON file of 2 MB at most; `Key`, the rule of every key.
- `BaseCatalogue`: about seventy themes in twelve categories, labelled in English, French, Spanish, German, Italian
  and Portuguese, in `resources/base-catalogue.php`.
- `ContentWarning`, `ContentWarnings` and `Severity`: one warning per theme with an optional severity and a note of
  500 characters, 100 warnings per piece of content, kept as an array and read back by `fromArray()`.
- `Display`, `Preference` and `WarningPreferences`: a reader's display per theme, per category and by default, each
  from an optional severity on, folded by default, kept as an array and read back by `fromArray()`.
- `WarningMatcher` and `Decision`: the strictest display of a piece of content's warnings, and the warnings that gave
  it.
- `Exception\ContentWarningsException`, `Exception\InvalidCatalogue`, `Exception\InvalidWarning` and
  `Exception\UnreadableWarnings`.
- The benchmark `WarningMatcherBench`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
