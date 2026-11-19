# Changelog

All notable changes to `phil/typography-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-18

The next version is 0.1.2-alpha: an optional key, on `phil/typography` 0.2.

### Added

- The `max_bytes` configuration: the longest text the typographers and the filters fix, in bytes, 1 MiB by default —
  the default of `phil/typography` —, a positive integer checked when the container is built, given to `Typographers`.
- The section [Ceiling of a text](doc/configuration.md#ceiling-of-a-text) of the configuration page.

### Changed

- `phil/typography` 0.2 is required: a text longer than its ceiling is refused by `InputTooLarge`.

## 0.1.1-alpha - 2026-11-17

The next version is 0.1.1-alpha: documentation only.

### Changed

- The README, in English and in French, says what the package does, without the wording of its first drafts.
- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.

## 0.1.0-alpha - 2026-11-11

### Added

- The services `Typographers`, `Typographer` for the default locale, and `phil_typography.typographer.<locale>` for
  each configured locale, also injected into an argument named after it.
- `PhilTypographyBundle` and the `phil_typography` configuration: `default_locale`, `hyphenation`,
  `experimental_hyphenation`, `protected_tags`, fixer names by locale checked against the presets, and the `ujo`
  integration with its locale, the current one when null.
- The Twig filters `typography`, on HTML with what is not marked safe escaped first, and `typography_text`, on plain
  text, each with an optional locale; without one, the current locale of Symfony's `LocaleSwitcher` — the request's,
  or the one `runWithLocale()` sets —, and `default_locale` when the translator is not installed.
- `TypographyHtmlFilter` and `UjoFilterPass`: the typographer of a locale, or of the current one, run on the HTML of
  every UJO document that `phil/ujo-bundle` renders.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
