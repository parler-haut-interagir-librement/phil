# Changelog

All notable changes to `phil/locale-dimensions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `Locale`: a language and an optional region with one spelling (`fr`, `fr_CA`), `sharesLanguageWith()`,
  `toLanguageTag()` for BCP 47 and `equals()`.
- `Version`: a version counted from one, `first()`, `of()`, `next()`, `isAfter()` and `equals()`.
- `FallbackChain`: the declared locales tried for a requested locale, `resolve()` against the locales that have
  something to serve, and `leavesRequestedLanguage()`.
- `DeclaredLocales`: the served locales and their chains from a declared array, a fallback that is not served refused.
- `Exception\InvalidLocaleDimension`, raised by every value refused as it is built.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
