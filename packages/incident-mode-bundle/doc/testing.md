# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What is covered |
|---|---|
| `tests/Unit` | The configuration and its refusals, every service and its arguments, the compiler pass, `DurationInput`, `Checkpoint` and its reads, the words in English and French, the commands, the attribute, the listener, the option of the forms, the schedule, the audit, the privacy |
| `tests/Functional` | An application on SQLite: the administration and its refusals, the role, the CSRF token, the journal's pages, the checkpoints of a controller, a class, a route, a code path and a form, the established members, the refusal, status and notice pages, the banner, the delayed message, the schedule, the pages drawn with the PHIL UX kit and with the Shadcn kit |
| `tests/Integration` | The same application on MySQL 8.4 and PostgreSQL 17: an incident run from the administration, refused, extended and recorded at its end once, the moments in UTC |

The integration tests read `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` and are skipped without them; in the
monorepo, `phil-dev services up` starts both servers. The functional application keeps its caches and its SQLite file
under the system's temporary directory.

## Fuzzing

`tests/Fuzz/duration-input.php` reads texts nobody wrote as durations: each gives a duration within the library's
bounds or `InvalidIncident`, never another error nor a warning, and a duration reads back from its own text.

```sh
composer fuzz -- duration-input
```

## Benchmark

`tests/Benchmark/CheckpointBench.php` runs the checkpoint of a sign-up and the banner of a page, on SQLite in memory
with a journal of 5,000 past incidents. On a recent laptop:

| Subject | No incident in force | Two incidents in force |
|---|---|---|
| The checkpoint of a sign-up | about 14 µs | about 35 µs |
| The banner of a visitor | about 13 µs | about 48 µs |

```sh
composer bench -- packages/incident-mode-bundle/tests/Benchmark
```

## Testing an application

Boot the application with a `MockClock` as the `clock` service, start an incident through `IncidentMode`, check that
the page refuses what it suspends with the status 503 and `Retry-After`, then move the clock past the end and check
that the page is open again. To test a page that spares established members, give `established_members` a service of
the test that holds the identifiers it chooses.
