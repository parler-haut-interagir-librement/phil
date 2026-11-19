# Changelog

All notable changes to `phil/trash-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-18

The next version is 0.2.0-alpha: the keys of the trashed items compare byte by byte on MySQL and MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the fields of
  the bundle's entities that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `id`, `resource_key`, `resource_id`, `parent` and `trashed_by` of `phil_trash_item` take the
  `utf8mb4` character set and the `utf8mb4_bin` collation in the schema the ORM generates, through
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL and SQLite keep their default, which compares bytes already.
- `doctrine/dbal` is required, already required by `doctrine/orm`: `BinaryKeysListener` reads the platform of the
  connection.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the trashed items of the resource `Doc-A` were
  found, restored and purged for `doc-a`.

## 0.1.0-alpha - 2026-11-11

### Added

- The entity `TrashItem`, the exceptions `TrashException`, `InvalidTrashItem`, `ParentMissing` and
  `UnknownResource`, and the port `TrashItemRepository`. Resources are `ResourceReference`s of `phil/resource`, a
  dependency, whose `InvalidResource` a malformed reference throws.
- `Trash`, which takes a required `ClockInterface`; `TrashHandler`, whose `remove()` removes rows without flushing
  and leaves files for `purge()`; `Captured`; the events `ResourceTrashed`, `ResourceRestored` and `TrashItemPurged`.
- `DoctrineTrashItemRepository`.
- `PhilTrashBundle`, extension alias `phil_trash`, keys `retention` and `retention_by_kind`; it wires the
  container's `clock` service.
- `PurgeTrashCommand`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
