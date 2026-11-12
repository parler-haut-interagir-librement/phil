# Testing

| Area | What is covered |
|---|---|
| Timestamps and blame | Entities stamped and blamed as they are persisted and updated, in UTC, nobody known, given values kept, entities without a behaviour left alone |
| Soft delete | A removal kept as a date, the original data the unit of work keeps, the filter on and off, restoring, a second removal deleting for good, an entity with a `deletedAt` of its own left visible |
| Deactivation | Switching off and on, the date kept in step, given dates kept, the filter on and off, an entity with no column of its own left visible |
| Owner | The identifiers refused and accepted, the owner given on persist, kept on update, transferred, nobody known, the eraser over tables and subclasses |
| Publication | The state machine with its dates, a transition refused, a date given kept, other subjects and places left alone |
| Slug | Made once, numbered, given slugs made unique, regenerated, rows hidden from the filters counted, cut to the column, inherited tables, every number taken, the last number tried |
| Versions | The fork after a freeze, what does not fork, freezing what is pending, the list and its limit, going back, the right to do it, an unknown number, unmapped state |
| Dated identifier | A UUID v7 of the clock's time, a given one kept, anything else refused, creation order |
| Positions | The end of the group for a record without position, given positions kept, groups of columns, enums and relations, moves before, after, first and last, the rows locked before each move, loaded records following, the groups refused |
| Translation | Rows created empty, never copied, read along a chain whole, pending and removed rows, the locales of a record, a list in one query per class and per chunk, the unique index, slugs and versions per locale, the mappings and translations refused |
| Trees | Paths of identifiers from the database and from the caller, roots, children, descendants, ancestors, the hierarchy and its order, branches moved with loaded and unloaded descendants, moves in one flush, LIKE wildcards in identifiers, nodes without a path, the places refused |

```sh
vendor/bin/phpunit packages/doctrine-behaviours/tests
```

## Real servers

`tests/Integration` runs what depends on a database, the spelling of true, the `IN` of the slugs, JSON state, `guid` keys,
the eraser, translations read along a chain and kept one per locale, and moves that wait for each other in one group but
not across groups, against MySQL and PostgreSQL, when `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` are set.
Without them the tests are skipped.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/doctrine-behaviours/tests/Integration
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/doctrine-behaviours/tests/Benchmark
```
