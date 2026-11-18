# Changelog

All notable changes to `phil/resource-permissions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-17

The next version is 0.2.0-alpha: the tuples are partitioned, and the table changes. See
[Upgrading from 0.1](doc/partitions.md#upgrading-from-01).

### Added

- `Contract\TupleScope`, the partition the current unit of work reads and writes; `SinglePartition`, every tuple in the
  default partition, and `FixedPartition`, every tuple in one named partition; `Partition`, the rule of a name — empty
  for the default partition, or 1 to 128 bytes of UTF-8 without control characters.
- `Exception\UnscopedTuples`: a scope without a partition while one is required fails the call before any read or
  write.
- `DbalTupleStore::schemaUpdate()`: the statements that create the table, bring an older one to this version, or
  nothing when it is current.
- The documentation of partitions, failing closed and the upgrade from 0.1; tests of two tenants with the same
  identifiers, and of the upgrade, on SQLite, MySQL 8.4 and PostgreSQL 17.

### Changed

- `DbalTupleStore` and `InMemoryTupleStore` take a `TupleScope`, `SinglePartition` by default, and every call reads,
  writes, forgets and purges in its partition alone. `purgeExpired()` purges the partition of the call.
- The table of `DbalTupleStore` has a `scope` column, `VARCHAR(128)` whose default is the default partition, first in
  the primary key and in the indexes `<table>_subject` and `<table>_expires_at`; on MySQL and MariaDB the names of
  kinds and relations take the `ascii` character set and `ascii_bin`, so that the key stays within 3,072 bytes.

### Security

- A tuple no longer authorises another tenant's resource of the same name: with identifiers local to a tenant, the
  tuples of every tenant shared one table and one key, and a check of one tenant read the tuples of all.

## 0.1.0-alpha - 2026-11-13

### Added

- `Subject`: one object, every object of a kind (`member:*`) or a subject set (`group:staff#member`), read back from
  its text.
- `RelationTuple`: a subject holds a relation on an object, with an optional expiry to the second; `RelationName`, the
  rule of a relation's name.
- The model: `Model`, `TypeDefinition` and `Rewrite` — direct relations with their accepted subject types, computed
  relations, relations read on related objects, union, intersection and exclusion —, checked when built.
- `RelationChecker`, behind `Contract\RelationCheck`: `check()`, `explain()` with the chain of tuples, and `objects()`
  listing the objects of a kind for a subject, gathered backwards then checked, ordered and limited.
- `Budget`: the depth, reads, fan-out and candidates of a walk; `TraversalBudgetExceeded` past them. Cycles are cut,
  a no found under a cut is never reused, an exclusion whose subtracted side met a cycle refuses.
- `RelationWriter`: tuples checked against the model, written or deleted at once, at most 1,000 per update; `forget()`.
- `Tree\TreePermissions`: entries per node and action for a role or everyone else, inherited down a tree of `parent`
  tuples, the nearest node then the roles then a denial winning; `move()`, `allow()`, `deny()`, `clear()`,
  `entries()`, `decide()`, and `InvalidTree` for two parents or a cycle.
- `Contract\TupleStore`, `InMemoryTupleStore`, and `Dbal\DbalTupleStore` on one table indexed by object and by
  subject, byte-by-byte collation on MySQL and PostgreSQL, expiry read by the clock, `purgeExpired()`, with
  `Dbal\TupleSchemaListener`; tested on MySQL 8.4 and PostgreSQL 17.
- The exceptions `InvalidRelation`, `UnknownRelation`, `TraversalBudgetExceeded` and `InvalidTree`, under
  `ResourcePermissionsException`.
- A fuzz target of the checker, `relation-checker`, and a benchmark of a check and a listing.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
