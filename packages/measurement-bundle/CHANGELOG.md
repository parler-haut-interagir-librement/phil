# Changelog

All notable changes to `phil/measurement-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `PhilMeasurementBundle` and its configuration `phil_measurement`: the units and families added to those of the
  library, the unit of each sales channel, checked when the container is built, and the services of the registry, the
  channels, the CLDR preferences and the formatter.
- `MeasurementType`, the form type of a value read in the request's locale, never as a float, and a unit offered by
  family, by list or alone, starting on the unit of a channel.
- `MeasurementRange` and `MeasurementUnit`, the constraints of a measurement's bounds, family and unit, with their
  messages in English and French.
- The Twig filters `format_measurement`, `measurement_in_channel` and `preferred_units`.
- `phil_measurement`, the Doctrine column type of a measurement as its exact text, read back by the application's
  registry.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
