# Changelog

All notable changes to `phil/access-codes` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-20

Writing a batch as CSV raises no deprecation on PHP 8.6.

### Fixed

- `CreatedBatch::writeCsv()` formats each line itself and writes it with `SplFileObject::fwrite()`, as
  `fputcsv()` does without an escape character, instead of calling `SplFileObject::fputcsv()`, deprecated on PHP 8.6.

## 0.2.0-alpha - 2026-11-18

Batch identifiers and code hashes compare byte by byte on MySQL and MariaDB. See
[Upgrading from 0.1](doc/storage.md#upgrading-from-01).

### Changed

- On MySQL and MariaDB, `DbalCodeStore::configureSchema()` gives `id` of the batches and `hash` and `batch_id` of the
  codes the `utf8mb4` character set and the `utf8mb4_bin` collation, given the connection's platform. PostgreSQL and
  SQLite keep their default, which compares bytes already.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, revoking the batch `Spring` also revoked the
  unredeemed codes of the batch `spring`, its counters added up both, and adding `spring` after `Spring` failed on the
  primary key.

## 0.1.0-alpha - 2026-11-13

### Added

- `CodeAlphabet`: 16 to 36 distinct digits and capital letters, Crockford's Base32 by default, text read in capitals
  without hyphens or spaces, `O`, `I` and `L` taken for `0` and `1` when the alphabet lacks them.
- `CodeFormat`: 1 to 64 random symbols carrying 40 bits at the least, `withEntropy()`, a Luhn mod N check symbol,
  groups of symbols when shown; `generate()` on a `Random\Randomizer`, `parse()` of what a person typed, bounded to
  256 characters, and `display()`.
- `CodeHasher`: the HMAC-SHA-256 of a code, `$hmac-sha256$v=1$` and 64 hexadecimal digits, under an ordered list of
  up to eight keys of 32 bytes or more: the first hashes, every key finds; `candidates()` and `matches()` with
  `hash_equals()`.
- `Batch` and `BatchCounts`: a named batch of 1 to 1,000,000 codes with an optional expiry and its revocation; the
  total, redeemed, revoked and unused codes.
- `AccessCodes`: `create()` of a batch of distinct codes from the secure generator, `redeem()` that spends one
  attempt of a Symfony rate limiter before any lookup and refuses a redeemer holding `@`, `revoke()`, `batch()` and
  `counts()`; `Redemption` and `RedemptionOutcome`: redeemed, unknown, already redeemed, expired, revoked, too many
  attempts.
- `CreatedBatch`: the codes in clear for one use, `writeCsv()` to an `SplFileObject` with prefilled links or `takeCodes()`, never
  serialized, cloned or shown by `var_dump()`.
- `RedemptionLinks`: the redemption page's address with a code in a query parameter, on `phil/url`.
- `Contract\CodeStore` and `CodeState`; `InMemoryCodeStore`; `Dbal\DbalCodeStore` on PostgreSQL, MySQL, MariaDB and
  SQLite: its schema, a batch added in one transaction 500 rows per statement, a redemption in one conditional
  `UPDATE`, a revocation in one transaction, tested on MySQL 8.4 and PostgreSQL 17 with eight processes racing.
- Exceptions under `AccessCodesException`: `InvalidCode`, `InvalidConfiguration`, `CodesAlreadyTaken`,
  `UnknownBatch`.
- The fuzz target `code-format` and the benchmark of a batch of 100,000 codes.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
