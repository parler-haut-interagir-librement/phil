# Upgrading

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
