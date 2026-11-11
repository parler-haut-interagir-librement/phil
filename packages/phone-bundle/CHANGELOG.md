# Changelog

All notable changes to `phil/phone-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
