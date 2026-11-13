# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Area | What is covered |
|---|---|
| The values | Restriction names and their bounds, durations and their bounds with overflows, actors and the refusal of addresses |
| Presets | Their checks, the built-in ones, the application's added, replaced and left out |
| Activations | In force, due, lifted, extended, what they suspend, every refusal |
| `IncidentMode` | Starting within the preset, checking, extending, lifting by hand, the expiry recorded once, the sweep, the journal, the events, a store that lost the race |
| The stores | One suite for the in-memory store, the DBAL store on SQLite, MySQL 8.4 and PostgreSQL 17: order, limits, conditional updates, UTC, actors found and forgotten, rows that no longer read |
| Messenger | The delay of the message, the handler lifting once due, the sweep |

The integration tests read `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` and are skipped without them. In the
monorepo, Infection holds the package at 100 % of covered mutants.

## Benchmark

`tests/Benchmark/IncidentModeBench.php` runs the check a page makes, on SQLite in memory with a journal of 5,000 past
incidents. On a recent laptop:

| Subject | Time |
|---|---|
| No incident in force | about 13 µs |
| Two incidents in force | about 35 µs |

```sh
composer bench -- packages/incident-mode/tests/Benchmark
```

## Testing an application

Give `IncidentMode` an `InMemoryActivationStore` and a `MockClock`, start an incident, check the page refuses what it
suspends, then move the clock past the end and check the page is open again.
