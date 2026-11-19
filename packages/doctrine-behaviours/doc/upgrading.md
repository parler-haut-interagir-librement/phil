# Upgrading

## From 0.2 to 0.3

### The personal fields of the versions

`phil_record_version` has a column `redacted_at`, nullable, of the type `datetime_utc_immutable`: the moment an erasure
took the personal fields out of a version. `doctrine:migrations:diff` writes the migration that adds it.

`Versions` leaves out the fields `PersonalFields` names by default, `PersonalFieldPolicy::Exclude`; without
`PersonalFields` it keeps every field, as before. The versions written before still hold those fields in plain: take
them out once, after the migration, with `VersionEraser::stripPersonalFields()`, or the bundle's command
`phil:doctrine-behaviours:strip-versions`:

```php
$eraser = new VersionEraser($registry, $personalFields, $clock);
$eraser->stripPersonalFields(dryRun: true);   // how many versions hold some
$eraser->stripPersonalFields();               // taken out; a sealed value stays
```

The record can still go back to those versions, with its own values for the fields taken out. An application that
erased people before this version runs `VersionEraser::redact()` for each of them, if their versions are still linked
to them through `owner_id` or `created_by`; once the blame or the owner is replaced, `stripPersonalFields()` is what
reaches those versions.

### The erasers

`BlameEraser` and `OwnerEraser` take an entity manager or a `ManagerRegistry`; given the registry, they reach every
entity manager. They write each connection in one transaction, evict the second-level cache regions of the classes
they change and detach the managed entities that hold the old value: flush before an erasure, since whatever such an
entity had pending is dropped.

### Collation of the keys

`BinaryKeysListener` is in the library: an application that uses the library without its bundle registers it on
`loadClassMetadata`, as [Integration](integration.md#collation-of-the-keys) shows. On MySQL and MariaDB,
`doctrine:migrations:diff` then writes the migration that brings the keys to `utf8mb4_bin`; on PostgreSQL and SQLite
the diff is empty. The join columns towards a dated identifier take its collation and are converted with it, and MySQL
refuses to change one side of a foreign key before the other: put `SET FOREIGN_KEY_CHECKS = 0` at the start of the
migration's `up()` and `SET FOREIGN_KEY_CHECKS = 1` at its end.

The change only makes values more distinct, so no existing row breaks a unique key, but rows whose keys differ only by
case, which a lookup by either spelling returned together, are each found by their own spelling from then on. Before
migrating, list the owners and authors written in more than one case, for each table that takes them:

```sql
SELECT owner_id, COUNT(*) AS row_count FROM notice
GROUP BY owner_id HAVING COUNT(DISTINCT CAST(owner_id AS BINARY)) > 1;
```

Keep them apart, or bring the rows to the spelling the application uses with an `UPDATE` before the migration.

## From 0.1 to 0.2

### Moments in UTC

The columns that mark a moment have the DBAL type `datetime_utc_immutable` instead of `datetime_immutable`:

| Trait or entity | Columns |
|---|---|
| `TimestampableEntity` | `created_at`, `updated_at` |
| `SoftDeletableEntity` | `deleted_at` |
| `DeactivatableEntity` | `deactivated_at` |
| `PublishableEntity` | `published_at`, `unpublished_at` |
| `Version\RecordVersion` | `frozen_at` of `phil_record_version` |

**The schema does not change.** Both types declare the same column on every platform — `DATETIME` on MySQL, MariaDB
and SQLite, `TIMESTAMP(0) WITHOUT TIME ZONE` on PostgreSQL —, and DBAL 4 keeps no type comment, so
`doctrine:migrations:diff` writes no migration and an existing table keeps working as it is.

**What a column holds is now read as UTC.** With `datetime_immutable`, DBAL wrote the wall-clock time of the
`DateTimeImmutable` it was given, without its zone, and read it back in the default time zone of PHP. With
`datetime_utc_immutable`, it writes the moment in UTC and reads it back in UTC, whatever either zone is.

- A stamp the listeners wrote in 0.1 was already in UTC: those rows need nothing. An application whose PHP default
  time zone is not UTC read them shifted by its offset in 0.1, and reads them right in 0.2.
- A moment the application gave itself — `stampCreated()`, `stampUpdated()`, `markDeleted()`, `stampDeactivated()`,
  `markPublished()` or `markUnpublished()` called with a `DateTimeImmutable` that was not in UTC — was written in its
  own zone. Such rows are read in 0.2 as if they were UTC, off by the offset of that zone. Convert them once, in the
  migration of the upgrade, from the zone they were written in:

```sql
-- PostgreSQL
UPDATE article SET created_at = (created_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC'
WHERE created_at < '2026-11-01';

-- MySQL and MariaDB, with the time zone tables loaded
UPDATE article SET created_at = CONVERT_TZ(created_at, 'America/Toronto', 'UTC')
WHERE created_at < '2026-11-01';
```

The condition is the application's: only it knows which rows an import or a command wrote with given dates. A row
converted twice is wrong by twice the offset, so the migration runs once and is kept in the migrations' history.

**A stamp keeps the clock's time zone in memory.** The listeners give the entity the clock's moment as it is, and DBAL
converts it as it writes. `createdAt()` right after the flush is `2026-11-04T08:00:00-05:00` for a clock in Toronto, and
`2026-11-04T13:00:00+00:00` once read again: the same moment. Code that formatted a stamp without a time zone, expecting
UTC, converts it with `setTimezone()`; a comparison of two moments uses `<`, `>` or their timestamps, never `==`.

An entity that overrides one of these columns with a `datetime_immutable` of its own, through `AttributeOverride` or
its own mapping, keeps the old type for that column; give it `datetime_utc_immutable` as well.

### Requirements

`doctrine/collections` 3.1 or later, where `Tree` orders by the native `\SortDirection`, and `symfony/polyfill-php86`,
which brings `\SortDirection` to PHP 8.5.
