# Changelog

All notable changes to `phil/slug` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Slug` (`fromString()`, `equals()`, `toString()`) and `InvalidSlug`.
- `Slugger`, the interface every slugger and decorator implements.
- `TransliteratingSlugger`: ASCII transliteration in a locale, lowercase words joined by hyphens, cut at a word.
- `LeadingWordsRemoved`: a decorator that drops one leading word of a list; `LeadingWordsRemoved::checkWord()`, the
  rule of a leading word, applied without building a slugger.
- `Sluggers`, the named sluggers of an application, and `UnknownSlugger`.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
