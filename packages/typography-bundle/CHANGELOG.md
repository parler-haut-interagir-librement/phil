# Changelog

All notable changes to `phil/typography-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
