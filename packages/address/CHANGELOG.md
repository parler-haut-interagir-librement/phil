# Changelog

All notable changes to `phil/address` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Address`, `AddressField`, `AddressFormat`, `AddressFormats`, `Countries`, `Subdivision`, `Subdivisions`,
  `AddressValidator`, `AddressViolation`, `Violation`, `AddressFormatter`, `AddressArray`; `AddressException`,
  `InvalidAddress`, `InvalidData` and `UnreadableAddress`. `Subdivisions::find()` looks a value up in an index of its
  list, built once, by the keys `Subdivision::keys()` gives, and `AddressFormat` reads the fields of its format once,
  when it is built, for `fields()` and `uses()`.
- `SchemaOrg`: the schema.org form of the library's value, to embed in JSON-LD.
- The formats and subdivisions of commerceguys/addressing v2.3.2 in `data/`, each file starting with
  `declare(strict_types=1);` as the repository's coding standard writes it, with `NOTICE`.
- `Data\DataImporter` and `bin/import-data.php`: every file is written in a staging folder before `formats/` and
  `subdivisions/` are replaced, so data refused halfway leave the data in place untouched.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
