# Changelog

All notable changes to `phil/opening-hours-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilOpeningHoursBundle`, extension alias `phil_opening_hours`, with the key `default_timezone`.
- `OpeningHoursColumnType`.
- `OpeningHoursType` and its transformers.
- `Intl`, which keeps its ICU formatters by language and style.
- `OpeningHoursExtension`, which takes a `ClockInterface`, the container's `clock` service, with no fallback to the
  system's time: `opening_hours_week()` gives each row a `heading`, its days with only their first letter made a
  capital, and `opening_hours_specials()` reads the moment in the place's time zone; the template
  `@PhilOpeningHours/table.html.twig`, which prints each row's heading.
- The labels and messages in English and French, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README; the README, `doc/installation.md` and the
  composer suggestions say that with Twig the container needs a `clock` service, `symfony/clock`'s.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
