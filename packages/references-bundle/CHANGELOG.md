# Changelog

All notable changes to `phil/references-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-18

The next version is 0.2.0-alpha: kinds, identifiers and fields of the usage table compare byte by byte on MySQL and
MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Changed

- `UsageTable::configure()` takes the platform of the connection as its second argument, and the schema listener gives
  it: on MySQL and MariaDB, `usage_key`, `source_kind`, `source_id`, `field`, `target_kind` and `target_id` of
  `phil_reference_usage` take the `utf8mb4` character set and the `utf8mb4_bin` collation. PostgreSQL and SQLite keep
  their default, which compares bytes already.

### Fixed

- Under the default case-insensitive collation of MySQL and MariaDB, refreshing the usages of the source `Doc-A` read
  those of `doc-a` and deleted them, and the usages of the target `doc-a` were listed for `Doc-A`.

## 0.1.0-alpha - 2026-11-11

### Added

- `Usage`, the exceptions `ReferencesException`, `InvalidReference` and `StillReferenced`, and the port
  `UsageRepository`, whose `usagesOfAll()` reads the usages of many targets at once. Resources are
  `ResourceReference`s of `phil/resource`, a dependency, whose `InvalidResource` a malformed reference throws.
- `References`, with `forgetSource()`; `ReferenceCollector`, `ReferenceTarget` and `Link`.
- `DbalUsageRepository`, which writes only the usages that changed and reads the usages of many targets by chunks of
  500 per kind; `UsageTable`, keyed by a SHA-256 of its five columns so that every key fits MySQL's limit, with
  indexes on the target and the source; `UsageSchemaListener`.
- `PhilReferencesBundle`, extension alias `phil_references`, keys `connection`, `track_entities` and
  `block_deletion`.
- `ReferenceTrackingListener`, which reads a deleted source's reference before the flush, notes what a flush will do
  per entity manager, and forgets it when the flush fails, the entity manager is cleared or the container is reset.
- `CheckReferencesCommand`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
