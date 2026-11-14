# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The configuration and its services; one contract of the five stores, held by the adapters in memory of `phil/stockroom` and by the bundle's on SQLite in memory; the rows read back; the tables, their collations and the schema listener; the command; the transactions, their lock statement, the order of the locks, the rollback and the joining of the caller's transaction. |
| `tests/Functional` | An application with FrameworkBundle and DoctrineBundle on SQLite: the tables in the schema the ORM generates, the stockroom and its deployments in a transaction, the command. |
| `tests/Integration` | The same contract of the stores on MySQL 8.4 and PostgreSQL 17, the byte-by-byte collations, and an item locked by a transaction seen from a second connection. |

The unit suite runs without a kernel, a server or a network. In the monorepo, Infection holds the package at 100 % of
covered mutants.

The integration tests read their server's URL from an environment variable and are skipped without it:
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`. They create their tables under the prefix
`phil_stockroom_test_`, dropping them first.

## Testing an application on the stockroom

Configure the store `memory` in the test environment for fast tests that do not need the tables: the adapters in
memory hold the same contract and are emptied between the requests of a worker. A test of the application's own
transactions with the stockroom keeps `dbal` on SQLite, with the tables created by `phil:stockroom:schema --force` or by
the ORM's schema tool, which the bundle's listener feeds.
