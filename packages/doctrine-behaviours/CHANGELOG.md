# Changelog

All notable changes to `phil/doctrine-behaviours` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.3.0-alpha, a privacy security fix: an erasure reaches the personal fields of the frozen versions
and every entity manager, and nothing it erased comes back. A minor version and not a patch: `phil_record_version` has
a column more, `Versions` leaves the personal fields out by default, the erasers take a `ManagerRegistry`, and the
library has new classes. After the migration that adds `redacted_at`, run `VersionEraser::stripPersonalFields()`, or
the bundle's command `phil:doctrine-behaviours:strip-versions`, to take the personal fields out of the versions already
written. See [Upgrading](doc/upgrading.md#from-02-to-03).

### Added

- `Contract\PersonalFields`, `NoPersonalFields` and `ListedPersonalFields`: which fields of a class hold personal
  information.
- `PersonalFieldPolicy` and `Contract\VersionCipher`: what a frozen version keeps of the personal fields, left out
  (`Exclude`), sealed by the cipher under the context of their record, version and field (`Encrypt`), or kept
  (`Keep`); `Versions` takes them as arguments, and `Versions::SEALED` names the key of a sealed value.
- `VersionEraser`: `find()` and `redact()` the versions of a person, those of the records the person owns or is blamed
  for and those the person froze; `redactRecord()` every version of one record; `stripPersonalFields()` the personal
  fields held in plain out of every version, by batches of 500, with a dry run; `tables()` for an erasure report.
- `Version\RecordVersion::redactedAt()`, `isRedacted()` and `id()`, and the column `redacted_at` of
  `phil_record_version`, a nullable `datetime_utc_immutable`.
- `Exception\VersionErased`: going back to a version an erasure redacted, or to an older one, is refused.
- `InvalidVersionedState::noCipher()` and `sealed()`: `Encrypt` without a cipher, and a sealed field to open without
  one.
- `BlameSuspension` and the argument `suspension` of `BlameListener`: no name blamed, on persist or on update, while an
  erasure runs in `during()`; spans nest, end when their work throws, and `reset()` ends them between the messages of a
  worker.
- `SoftDeletes::purge()`: the row of a `SoftDeletable` entity deleted for good in one flush, soft deleted before or not.
- `BinaryKeysListener` and its `FIELDS`, on the ORM's `loadClassMetadata` event: the keys the behaviours write compare
  byte by byte on MySQL and MariaDB for an application that uses the library without its bundle; registered by the
  bundle, which now registers this class.
- `doc/bulk-writes.md`: what no listener sees, `postFlush` before the commit, the blame and the slug that copy personal
  information, the row a soft delete keeps.

### Changed

- `Versions` leaves the fields `PersonalFields` names out of a frozen version by default, and going back keeps the
  values the record holds for them; without `PersonalFields`, every field is kept, as before.
- `BlameEraser` and `OwnerEraser` take an entity manager or a `ManagerRegistry`, whose every entity manager they reach,
  each table once per connection; they write the tables of each connection in one transaction of that connection,
  then evict the second-level cache regions of the classes they changed and detach the managed entities that still
  hold the old value.
- `SoftDeleteListener` and `SoftDeletable` say that a first removal keeps the whole row, and that the listener is
  registered after the `onFlush` listeners that must see a deletion.
- `doctrine/persistence` is required, for `ManagerRegistry`; it was already required by `doctrine/orm`.

### Security

- An erasure left the personal fields of a record in the JSON `state` of its frozen versions, which `BlameEraser` did
  not reach, and `Versions::restore()` could put an erased value back on the record.
- `BlameEraser` and `OwnerEraser` reached the default entity manager only, while the listeners ran on every connection;
  an entity loaded before the erasure, or held in the second-level cache, still gave the old name.
- On MySQL and MariaDB, an application that used the library without its bundle compared the keys under the default
  case-insensitive collation: the records of the owner `Ann` were listed and erased for `ann`.

## 0.2.0-alpha - 2026-11-14

### Added

- `doc/upgrading.md`: the upgrade from 0.1 to 0.2, and the conversion of the moments an application wrote itself in
  another time zone.

### Changed

- The columns that mark a moment — `created_at`, `updated_at`, `deleted_at`, `deactivated_at`, `published_at`,
  `unpublished_at` and the `frozen_at` of `phil_record_version` — have the DBAL type `datetime_utc_immutable`: written
  and read back in UTC, whatever the time zone of PHP or of the database. The SQL of the columns is the same, so no
  migration of the schema is written; rows an application stamped itself in another time zone are converted once, as
  the upgrade guide shows.
- The listeners and `Versions` stamp the clock's moment as it is, in the clock's time zone until the entity is read
  again; DBAL converts it to UTC as it writes.
- `Tree` orders by the native `\SortDirection`; the package requires `doctrine/collections` 3.1 or later and
  `symfony/polyfill-php86`.

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
