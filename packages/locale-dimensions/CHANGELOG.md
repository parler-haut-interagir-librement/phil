# Changelog

All notable changes to `phil/locale-dimensions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-14

### Added

- `DeclaredLocales::fromArray()`: a served locale declared with `null` gets the default chain — the parents
  Symfony's `LocaleFallbackProvider` derives from ICU (`fr_CA → fr`), then the default fallbacks given as the second
  argument, each kept only when it is served; a default fallback that is not served is refused.
- `Locale::tryFromString()`: the locale, or `null` for a string that cannot be one, for a locale read from a URL, a
  form, a header or a stored row.

### Changed

- The package requires `symfony/translation` 8.2 or later, for `LocaleFallbackProvider`.

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
