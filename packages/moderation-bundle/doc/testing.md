# Testing

## The bundle's tests

```sh
vendor/bin/phpunit packages/moderation-bundle/tests
```

| Area | What is covered |
|---|---|
| The domain | Members and the refusal of addresses, targets, identifiers, reports, decisions, statements of reasons, notes, the queue lines |
| The services | Reports once per member, refusals; decisions applied and closing, roles and their types; statements of reasons, their grounds checked before the action, read by their member only; the queue by role; the sandbox left on approval; the watched words; temporary hiding; notes; the journal without names; the surge |
| The stores | One suite on SQLite in memory, MySQL 8.4 and PostgreSQL 17: order, grouping, pages, types, UTC, forgetting, rows that no longer read |
| The adapters | The token's member and moderator, the export and the erasure, the proposal of the incident mode, the notification of a statement, the Twig functions, the schema listener, the EasyAdmin helpers |
| The bundle | Every option and its bounds, the services without SecurityBundle, the passes |
| The pages | A kernel on SQLite with the recipes of the kit: the report form, the queue, a decision and its statement of reasons read by the author, the notes, the queue offering the page of `phil/incident-mode-bundle` and proposing an incident, a sign-up, an invitation and a form refused through its checkpoints, the journal, every refusal |
| EasyAdmin | A dashboard with the pages grafted, a decision in the back office, no incident mode offered without its bundle |

The integration tests read `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` and are skipped without them. In the
monorepo, Infection holds the bundle at 100 % of covered mutants.

## Benchmark

`tests/Benchmark/ModerationBench.php` runs both paths on SQLite in memory with 10,000 reports on 2,000 targets, half of
them decided. On a recent laptop:

| Subject | Time |
|---|---|
| A page of 25 lines of the queue, with its count | about 10 ms |
| The submission of a content by a member out of the sandbox | about 2 µs |

```sh
composer bench -- packages/moderation-bundle/tests/Benchmark
```

## Testing an application

A target of the application is tested against `ModerationTarget`'s contract: `describe()` gives `null` for a content
that no longer exists, and `apply()` may be called twice. The pages are tested with a kernel that registers the
target, a `CurrentMember` reading the test's users, and the recipes of the kit.
