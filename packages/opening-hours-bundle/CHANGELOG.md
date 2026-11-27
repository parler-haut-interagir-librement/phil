# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/opening-hours-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

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
