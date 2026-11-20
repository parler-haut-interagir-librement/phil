# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Register`, `CashRounding` | Every rule, rounding to the nearest increment both ways |
| `CashSession`, `CashMovement` | The float, each kind of movement, the close, over and short, a closed session |
| `RegisterSale`, `SessionPayment`, `HeldSale`, `Layaway`, `ImportedSale` | Every rule, the life of a layaway |
| `Registers`, `CashSessions`, `SessionReport` | One open session per register, one main register per location, PIN terminals at the main register only, the end-of-day report in full |
| `TerminalPayments`, `TerminalGateway`, `TerminalAdapters`, `FakePaymentTerminal`, `TerminalNotificationController` | A charge presented, captured and counted once, failed, cancelled, refunded; a device that fails; forged and unknown notifications; every answer |
| `SalePresets`, `CustomerDisplay`, `TillDraws`, `CashDifferences` | Presets and the customer identified, the display without the person, dice entered once paid, the report's period and threshold |
| `CashierSwitch` | Off by default, weak PINs refused, hashing and rehashing, the rate limit by register and user, a missing PIN read as a wrong one |
| `RegisterCheckout`, `RegisterPayments`, `RegisterGateway` | Holding, tipping, ringing up; a session at prices that include the taxes, a cart priced otherwise refused; cash with rounding and change, recorded methods, split payments, refunds |
| `Layaways`, `OfflineSale`, `OfflineSales` | Deposits, completion, cancellation; reading a sale, importing once, re-pricing, capping payments, the main register only, never over an existing order |
| `OfflineSaleController` | Each answer |
| Mapping | Every column of the twelve tables |
| `DoctrinePosRepository` | `tests/Integration`: what is kept read back and each query's filters and order, moments kept in UTC, on SQLite in memory always, and on MySQL 8.4 and PostgreSQL 17 when their URLs are set |
| `PhilCommercePosBundle` | The configuration and its bounds, the services with DoctrineBundle, nothing without, the mapping, the refusal without the core, a charge on the fake terminal settled through the controller, the cashier switch off and on, the Stimulus controller found by StimulusBundle through AssetMapper and fetched lazily |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `RegisterFlowTest` runs a day — a held sale, a tipped cash sale, a layaway, a drop, the close. Installed
on its own, where FrameworkBundle is a development requirement, the suite is skipped.

`tests/js` runs the offline queue, its IndexedDB store and its Stimulus controller under Node's test runner, Stimulus
resolved to a stub and IndexedDB to a small fake that settles its requests as a browser does.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-pos-bundle/tests
```

```sh
node --import ./packages/commerce-pos-bundle/tests/js/register.mjs --test packages/commerce-pos-bundle/tests/js/offline-queue.test.mjs
```

The integration suite runs on MySQL and PostgreSQL with the servers of `phil/dev-tools`, in Docker; without their URLs,
those two are skipped. It drops and creates the module's tables in the test database:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/commerce-pos-bundle/tests/Integration
vendor/bin/phil-dev services down
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `CashSessions::report()` on SQLite in memory, in a fresh unit
of work, for sessions of 20, 200 and 800 sales. From the monorepo root:

```bash
composer bench -- packages/commerce-pos-bundle/tests/Benchmark
```
