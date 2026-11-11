# Changelog

All notable changes to `phil/instant-messenger-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `AcceptedMessengers`.
- `PhilInstantMessengerBundle`, extension alias `phil_instant_messenger`.
- `HandleType` and `HandleTransformer`, which shows a handle as people read it only when its service reads that text
  back to the same handle, and its canonical form otherwise, so an edit form submitted unchanged always validates;
  `HandleOfType`, whose `@param` names the `EnumSet` it accepts, and its validator.
- `MessengerExtension`.
- The messages in English and French, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README; the storage example of
  `doc/forms-and-validation.md` takes the handle back with `Handle::fromCanonical()`, so a phone number the numbering
  plans do not know does not stop the entity from loading.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
