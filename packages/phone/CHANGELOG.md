# Changelog

All notable changes to `phil/phone` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `PhoneNumber`, `PhoneNumberType`, `PhoneFormat`; `PhoneException`, `InvalidPhoneNumber` and `InvalidMetadata`.
  `PhoneNumber::region()` and `type()` find the region and the type in one pass, kept while the number lives.
  `PhoneNumber::parse()` refuses an input longer than `PhoneNumber::MAX_INPUT`, 100 characters, before any pattern
  runs on it, so that its time stays bounded whatever is sent.
- `Metadata\Territory`, `NumberDesc`, `NumberFormat` and `Territories`. `Territories` builds the sorted list of
  countries once a process and finds a country in it by key; `NumberDesc` builds its pattern once.
- The numbering plans of libphonenumber v9.0.42 in `data/`, each file opening with `declare(strict_types=1);`, with
  `NOTICE` and the Apache License 2.0.
- `Metadata\MetadataImporter` with `bin/import-metadata.php`. `MetadataImporter::import()` reads every territory
  before anything is written, renames each file over the old one, and removes the dropped plans last, so a failed
  import leaves every plan whole, the old one or the new one. A file that cannot be written is an
  `InvalidMetadata::unwritable()`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
