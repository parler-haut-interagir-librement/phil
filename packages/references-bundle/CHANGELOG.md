# Changelog

All notable changes to `phil/references-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
