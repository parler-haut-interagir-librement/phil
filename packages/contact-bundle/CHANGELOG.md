# Changelog

All notable changes to `phil/contact-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

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
