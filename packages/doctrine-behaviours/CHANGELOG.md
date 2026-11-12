# Changelog

All notable changes to `phil/doctrine-behaviours` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Contract\Timestampable`, `TimestampableEntity` and `TimestampListener`: `created_at` and `updated_at`, in UTC, by the
  clock.
- `Contract\Blameable`, `BlameableEntity`, `BlameListener`, `Contract\BlameResolver` and `FixedBlame`: `created_by` and
  `updated_by`, the identifiers of the users.
- `BlameEraser`: finds and replaces a user's name in every blamed table, soft-deleted rows included, for access and
  erasure requests; `tables()` lists the blamed tables.
- `Contract\SoftDeletable`, `SoftDeletableEntity`, `SoftDeleteListener` and `SoftDeletedFilter`: a removal becomes
  `deleted_at`, hidden from queries; removed once more, deleted for good.
- `Contract\TreeNode`, `TreeNodeEntity`, `TreeListener`, `Tree`, `Branch` and `InvalidTree`: materialized paths,
  branches moved with their node, roots, children, descendants, ancestors and the hierarchy. `Tree::children()` reads
  one level, by depth. `TreeListener` starts each flush with no move waiting, and `TreeListener::reset()` forgets the
  moves waiting for a flush to end.
- `Contract\Deactivatable`, `DeactivatableEntity`, `DeactivationListener` and `DeactivatedFilter`: a record switched
  off and back on, `active` and `deactivated_at`, hidden from queries while it is off.
- `OwnerId`, `Contract\Ownable`, `OwnableEntity`, `OwnerListener`, `Contract\OwnerResolver`, `FixedOwner`, `OwnerEraser`
  and `Exception\InvalidOwner`: the owner of a record by a stable identifier, an `@` refused as it is built, found and
  replaced in every owned table for access and erasure requests; `OwnerEraser::tables()` lists the owned tables.
- `Contract\Publishable`, `PublishableEntity`, `PublicationWorkflow` and `PublicationListener`: a publication state of
  `symfony/workflow`, with the dates of publication and withdrawal.
- `Contract\Positionable`, `PositionableEntity`, `PositionListener`, `Positions` and `Exception\InvalidScope`: an
  order within an optional group of columns and to-one relations, a record with no position sent to the end of its
  group, and moves before, after, first and last in one transaction, each locking the row it is placed against or,
  first and last, its whole group.
- `Contract\Sluggable`, `SluggableEntity`, `ScopedSluggableEntity`, `SlugListener` and `Exception\SlugUnavailable`: a
  slug by `phil/slug`, made once and numbered to stay unique in its table, or within the scope `slugScope()` names,
  such as a tenant, a locale or a parent.
- `Contract\Translatable`, `Contract\Translation`, `TranslationEntity`, `TranslationListener`, `Translations`,
  `TranslationSet` and `Exception\InvalidTranslation`: the text of a record in a row per locale, with a unique index on
  the record and the locale, read along a fallback chain of `phil/locale-dimensions`, a list in one query per class,
  written without copying from another locale; the slug of a translation is unique within its locale.
- `Contract\Versionable`, `VersionableEntity`, `VersionListener`, `Versions`, `Version\RecordVersion`,
  `Contract\VersionRestoreGuard`, `AllowVersionRestore`, `RefuseVersionRestore`, `Exception\UnknownVersion`,
  `Exception\VersionRestoreDenied` and `Exception\InvalidVersionedState`: frozen versions numbered from one, the next
  edit forking the next number, and going back to a past version under a right of its own.
- `Contract\DatedIdentifiable`, `DatedIdentifierEntity` and `DatedIdentifierListener`: a UUID v7 as the primary key.
- The documentation in `doc/`, indexed by the README; the soft delete documentation says what a cascaded removal
  does to each kind of child, and that a to-one relation to a soft-deleted row throws `EntityNotFoundException`
  under the filter.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
