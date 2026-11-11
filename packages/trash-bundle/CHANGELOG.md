# Changelog

All notable changes to `phil/trash-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
