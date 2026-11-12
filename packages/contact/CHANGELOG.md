# Changelog

All notable changes to `phil/contact` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `ContactCard`, `Email`, `PhoneLine`, `PhoneRole`; `VCard`, `SchemaOrg`, `ContactCardArray`; `ContactException`,
  `InvalidContact` and `UnreadableContact`. `Email::of()` keeps the whole local part when lowering the domain changes
  its length in bytes (`ada@İnfo.com`). `VCard` escapes a lone carriage return in a text value, so a value cannot
  start a new vCard property.
- `PersonName`, a person's name in its parts — given name, additional names, particle, family name, second family
  name, honorific, generation, credential, culture and order —, with `display()`, `formal()`, `sortKey()` and
  `sortFamily()`; `NameOrder`; `NameConvention` and `NameConventions`, the order and sort key of the names of each
  culture, tested on real names. `ContactCard`'s `structuredName`, which gives the card its name when it has none;
  `N` in `VCard`, with `LANGUAGE`, `SORT-AS` and the components of RFC 9554; the name's parts in `SchemaOrg`; the
  `structuredName` key in `ContactCardArray`, missing from cards stored without one.
- `JSContactCard`, `JSContactWriter`, `JSContactReader`, `JSContactNotice` and `UnreadableJSContact`: JSContact 1.0
  (RFC 9553) written and read, postal addresses as `phil/address` values, the properties the library does not map kept
  and written back, a notice for what is read but not kept; the reader strict and bounded in bytes, depth and entries,
  with a fuzz target.
- The documentation in `doc/`, one file per topic, indexed by the README; `doc/storage.md` says how to encrypt a stored
  card with `#[Encrypted]` and search it with `#[BlindIndexed]` of Symfony's KeyManagement component.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
