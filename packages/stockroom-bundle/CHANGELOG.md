# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/stockroom-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- Requires the latest minor of each dependency (`doctrine/dbal` 4.6), and of each development requirement
  (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4).

## 0.1.0-alpha - 2026-11-14

### Added

- `PhilStockroomBundle` and its configuration `phil_stockroom`: the store, `dbal` or `memory`, the connection, the
  table prefix, checked when the container is built, and the clock.
- `StockroomTables`: the six tables under one prefix, identifiers compared byte by byte (`ascii_bin` on MySQL and
  MariaDB, `C` on PostgreSQL), and `configureSchema()`.
- `DbalContainers`, `DbalItems`, `DbalBalances`, `DbalJournal` and `DbalLoadLists`: the five ports of
  `phil/stockroom` on a Doctrine DBAL connection, held to the contract of the library's adapters in memory; the journal
  written once and ordered by an auto-incremented position, moments to the microsecond in UTC; every row read checked
  again by the library.
- `StockroomTransactions`: the calls of the stockroom in one transaction, the rows of their items locked in order,
  joining a transaction of the caller on the same connection; the parameter `phil_stockroom.connection` for the
  packages that share it.
- `StockroomSchemaListener`, which adds the tables to the schema the ORM generates, and `phil:stockroom:schema`, which
  shows or creates the missing tables.
- `Stockroom`, `Deployments`, `LoadListReader` and `StockReader` as services on the configured stores.
- Tests on SQLite, MySQL 8.4 and PostgreSQL 17, a test application, and the Flex recipe in `recipe/0.1`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
