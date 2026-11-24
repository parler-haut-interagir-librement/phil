# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit/Domain` | The ledger on the repository in memory: five copies bought and five consigned of one SKU, sales under each issue order, returns, transfers, counts, margins, units given back and bought out, and refusals; agreements, periods, statements and payouts |
| `tests/Unit/Application` | `ConsignmentStock` with recorded stock movements, `ShippedOrders` with orders of `phil/commerce-bundle`; agreements, statements over two months with a return of an earlier sale, payouts and reversals, units due and given back; the CSV, the data and the template of a statement |
| `tests/Unit/Infrastructure` | The logistics adapters on the real services of the logistics module — customer returns, adjustments, receipts against purchase order lines, the listener of `UnitsShipped` —, the repositories in memory and on SQLite; two requests writing the payout of one key, or reversing one payout, at once; the statements' series |
| `tests/Functional` | A kernel with the core, the logistics module and the bundle: a receipt, an order shipped, its sale written when the logistics module announces the shipment, two shipments in one flush, `RecordShipment` writing nothing twice, a customer return; a statement, its payout and the unsold copies given back |
| `tests/Integration` | The Doctrine repositories on MySQL 8.4 and PostgreSQL 17, keys compared byte by byte, and the same concurrent payouts |

The unit suite runs without a kernel, a database server or a network. In the monorepo, Infection holds the package at
100 % of covered mutants.

The integration tests read the URL of their server from `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, and are
skipped without it; in the monorepo, `composer test:services` starts the servers and sets them.
