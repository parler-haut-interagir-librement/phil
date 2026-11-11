# Changelog

All notable changes to `phil/tax` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `TaxRate`: a code and a share in parts per million; `of()`, `ofPercent()`, `percent()`, `equals()`, and `taxOn()`
  rounding half away from zero in integer arithmetic.
- `TaxLine`, computed or reported, and `reversed()`.
- `TaxBreakdown`: lines on one base, each rate code once; `line()`, `totalTax()`, `totalWithTax()`, `reversed()`.
- `MultiRateTaxCalculator`: every rate on the same base, one line each; `extract()`, the taxes included in a price,
  and the base they leave.
- `TaxException`, implemented by `InvalidTaxRate`, `InvalidBreakdown` and `UntaxableBase`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
