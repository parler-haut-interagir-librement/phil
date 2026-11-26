# Changelog

All notable changes to `phil/phone-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

### Security

- `PhoneNumberTransformer` refused a number in no numbering plan with a message that held the number, and passed on
  the messages of `phil/phone` that held the text typed: a message that reached a log kept personal data there. They
  name the reason only.

## 0.1.1-alpha - 2026-11-17

Documentation only.

### Changed

- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilPhoneBundle`, extension alias `phil_phone`, with the key `default_region`.
- `PhoneNumberColumnType`: `phil_phone` is a `VARCHAR(33)`, `PhoneNumberColumnType::LENGTH`: the longest number
  `PhoneNumber::parse()` accepts, with a three-digit country code, seventeen national digits and a seven-digit
  extension, fits.
- `PhoneType` and `PhoneNumberTransformer`; the field's `maxlength` is `PhoneNumber::MAX_INPUT`.
- `ValidPhoneNumber` and its validator, which asks a number its type once, settling both its validity and the
  accepted types.
- `PhoneExtension`.
- The messages in English and French, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
