# Testing

| Area | What is covered |
|---|---|
| `ScheduledTransition`, `ResourceReference`, the exceptions | Every check; times in UTC; moves, cancels, attempts; the mapping |
| `Scheduler` with `DoctrineScheduledTransitionRepository` | On SQLite in memory: scheduling and moving, runs, retries and giving up, cancels, batches, the soonest first |
| `RunTransitionsCommand`, `TransitionsSchedule`, `RunDueTransitionsHandler` | Limits; the recurring message and its trigger |
| `WorkflowTransitionHandler` | A workflow's transition applied and saved; a transition not allowed; no workflows |
| `PhilScheduledTransitionsBundle` | Services, settings, the mapping |
| `BinaryKeysListener` | The keys in `utf8mb4_bin` in the schema generated for MySQL and MariaDB, the default collation on SQLite, the options a field already has kept |

The suite `tests/Integration` runs `BinaryKeysListener` on a real MySQL 8.4 and PostgreSQL 17: the transitions of four resources whose identifiers differ only by case or accent are each found by their own resource only, and on
MySQL the keys are in `utf8mb4_bin` and the tables of the previous version reach it through the schema diff. Each
server's test reads its URL from `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/scheduled-transitions-bundle/tests/Integration
```

A functional suite boots a kernel with FrameworkBundle and a `page` workflow, DoctrineBundle on SQLite and the bundle:
a page is published and unpublished as a moved clock reaches each moment.

```sh
vendor/bin/phpunit packages/scheduled-transitions-bundle/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/scheduled-transitions-bundle/tests/Benchmark
```
