# Changelog

All notable changes to `phil/measurement` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `UnitRegistry`, the families and units read from data: length, mass, volume, area, temperature and temperature
  difference, duration, speed, fuel consumption, energy, pressure and power, with UCUM codes and exact factors;
  `withDefinitions()` adds families and units from a configuration, checked and bounded.
- `Measurement`, an exact value in a unit, never a float: conversions that keep every digit in a chain, arithmetic
  within a family, temperatures that take differences, reciprocal units such as miles per gallon, rounding in every
  `RoundingMode`, splits over several units, and a text form that reads back.
- `ChannelConversions`, the unit each sales channel shows a family in.
- `UnitPreferences`, the unit a region writes a quantity in for a usage, from the unit preferences of CLDR.
- `UnitFormatter`, a measurement written in a locale in three widths from the unit patterns of CLDR, with the number
  symbols, plural rules and list patterns of ICU.
- The CLDR 49 data under `data/cldr/`, under the Unicode License v3, and `bin/import-cldr.php` to import another
  release.
- The exceptions, all under `MeasurementException`.
- Fuzz targets for the reading of a measurement and of unit definitions, and benchmarks of the conversions.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
