# Changelog

All notable changes to `phil/money` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Currency`: an ISO 4217 code the Unicode CLDR data knows, with its fraction digits, localized name and symbol; one
  instance per code, looked up in the CLDR data once per process.
- `Money`: integer minor units with a currency; `of()`, `zero()`, `ofDecimal()`, `sum()`, `toDecimal()`, `add()`,
  `subtract()`, `negate()`, `abs()`, `multiply()`, `compare()`, `isGreaterThan()`, `isLessThan()`, `equals()` and the
  sign tests; `allocate()` by ratios, by largest remainder, and `split()` into equal parts, neither losing a unit; a
  symmetric integer range, overflows refused.
- `MoneyException`, implemented by `UnknownCurrency`, `InvalidAmount` and `CurrencyMismatch`.
- `SchemaOrg`: the schema.org form of the library's value, to embed in JSON-LD.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
