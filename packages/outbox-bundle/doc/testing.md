# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel or a network: the DBAL store passes the contract tests of
phil/outbox on SQLite in memory, and the relay, the runner and the commands run on a frozen clock. The functional suite
boots a small application with FrameworkBundle and DoctrineBundle on SQLite. In the monorepo, Infection holds the
package at 100 % of covered mutants.

The integration suite runs the contract tests and the transaction tests on real servers, read from the environment and
skipped without them: MySQL 8.4 at `PHIL_TEST_MYSQL_URL`, PostgreSQL 17 at `PHIL_TEST_POSTGRESQL_URL`. In the monorepo,
`vendor/bin/phil-dev services up` starts them in Docker.

## The benchmark

`tests/Benchmark/DbalRelayBench.php` measures a hundred events recorded in one transaction, then relayed in one batch
of the DBAL store on SQLite in memory: `composer bench`.

## In an application's tests

The `memory` store keeps the records in the process, reset between requests; the `Relay` service relays them on
demand:

```yaml
# config/packages/test/phil_outbox.yaml
phil_outbox:
    store: memory
```

```php
$service->complete($holder, $set);
self::getContainer()->get(Relay::class)->relay();
```
