# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the router, the switch, the copy
plan and the `dbal` stores on an in-memory SQLite, the statements of MySQL recorded by a connection that never opens,
the migration runner with a small PHP script standing in for the console, stopped while it runs. In the
monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/sealed-credentials.php` opens values nobody wrote with `CredentialSeal`, as they are and sealed as plain
text, and checks that each opens as credentials or is refused, never with another error or a warning:

```sh
vendor/bin/phil-dev fuzz sealed-credentials --budget=60
```

## The integration tests

`tests/Integration/MigrationStopTest.php` needs no server: it starts the migration command in a console of its own
with slow migrations, sends it SIGTERM or SIGINT, or ends it with a fatal error, and checks that no migration process
is left running. It needs the `pcntl` extension.

`tests/Integration` boots a small application — FrameworkBundle, DoctrineBundle, `phil/tenancy-bundle` and this bundle,
three hosts and two dedicated tenants — on a real server, once on MySQL and once on PostgreSQL. Each test drops and
creates its databases, so the account must be allowed to create them; the test is skipped otherwise, and without a URL.

| Server | Variable read first | Then |
|---|---|---|
| MySQL | `PHIL_TENANCY_DATABASE_TEST_MYSQL_URL` | `PHIL_TEST_MYSQL_URL` |
| PostgreSQL | `PHIL_TENANCY_DATABASE_TEST_POSTGRESQL_URL` | `PHIL_TEST_POSTGRESQL_URL` |

```sh
vendor/bin/phil-dev services up
PHIL_TENANCY_DATABASE_TEST_MYSQL_URL='mysql://root:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit tests/Integration
```

The MySQL account `phil` of `phil-dev services` may only use `phil_test` and cannot create databases: the MySQL tests are
then skipped, each with the reason. Give the root account, whose password is `phil` in those services, in
`PHIL_TENANCY_DATABASE_TEST_MYSQL_URL`, as above; the variable is read by the tests of this bundle only, so the other
packages keep the restricted account. The PostgreSQL account is the server's own, which may create databases.

Each server runs two suites. `MySqlTenantDatabasesTest` and `PostgreSqlTenantDatabasesTest` keep the placements in
memory; they cover two tenants of each kind read one after the other in one request, with no row of one seen by the
other — through the entity manager, native SQL and `find()` on the same key —, the refusal inside a transaction, the
reset of a worker, two tenants taking turns in one worker, the creation of a database, a migration that fails for one
database while the others succeed and are served, and moves that succeed, collide on a key or lose their placement
store, each rolled back to the source.
`MySqlStoredPlacementsTest` and `PostgreSqlStoredPlacementsTest` keep the placements and the suspensions in the tables
of the shared database: the tables created with the schema, the placements read from the shared database while a
dedicated tenant is entered, a move recorded in the table and back, and suspensions that survive a cleared cache and the
end of the unit of work, which closes the store connection.

`MySqlPlacementKeysTest` and `PostgreSqlPlacementKeysTest` create no database: in the test database of
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, they place the tenants `Rose`, `rose`, `ROSE` and `rosé` in four
databases and read each placement back. A row is found by the SHA-256 of the tenant reference, so that the default
collation of MySQL, which folds case and accents, never makes two tenants one.

`testTwoTenantsTakeTurnsInOneWorker` serves requests with one kernel as a FrankenPHP worker does — each request
handled, then terminated, and the services reset as the next one starts — for the shared tenant, the dedicated one,
the shared one again, then a request of each that throws in the middle of a transaction, and one of each after that.
Each request finds the database of its tenant selected, an empty identity map, no transaction open, the first invoice
of its own tenant on a key both tenants use, and only its own tenant's cache items; the entity manager is cleared as
the tenant is entered and as it is left at `kernel.terminate`; the writes of the requests that threw are rolled back.

## Testing an application

- **Isolation**: seed two tenants, one shared and one dedicated, with rows on the same keys; enter one, then the other,
  in one test, and read through the repository, native SQL and `find()`.
- **Workers**: serve several requests with one kernel, calling `handle()` then `terminate()` for each and never
  rebooting it, for tenants of different databases in turn, including a request that fails in a transaction.
- **Moves**: run `phil:tenancy:database:move` against a copy of production data before a real move; the command
  verifies every table, and rolls back on any difference.
- **Migrations**: run `phil:tenancy:database:migrate --concurrency=1` in the pipeline, against databases created by
  `phil:tenancy:database:create`.
