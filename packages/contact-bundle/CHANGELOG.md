# Changelog

All notable changes to `phil/contact-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `ContactCardType`, `EmailAddressType`, `PhoneLineType` and their transformers; the form theme
  `@PhilContact/form/theme.html.twig`, which gives the collection controller the index after the highest key, so an
  entry added after a failed submission never takes the key of a kept entry; the Stimulus controller `collection`.
- `PersonNameType` and `PersonNameTransformer`, a person name in its parts with the `autocomplete` tokens of its
  fields, and the `structured_name` and `culture` options of `ContactCardType`, the structured name giving the card the
  name it lacks.
- `ContactCardColumnType`, which keeps the structured name in the same column; `VCardResponse` and
  `JSContactResponse`, which turn a slash or a backslash in the file name into `_`; `ContactExtension`, whose
  `contact_json_ld` adds the card to the page's graph of phil/structured-data-bundle when that bundle is installed,
  and prints its own script element otherwise, and whose `person_name` writes a name displayed, formal or sorted.
- The `JSContactReader` service of phil/contact, its bounds and region under `phil_contact.jscontact`.
- The labels and messages in English and French, in `translations/`.
- `PhilContactBundle`, extension alias `phil_contact`, and its configuration.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
