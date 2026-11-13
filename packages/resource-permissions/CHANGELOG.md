# Changelog

All notable changes to `phil/resource-permissions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
