# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Instrument`, `Movement`, `MovementKind`, `GiftCardCode` | Every rule, expiry, voiding, the history of a balance, reading and generating codes |
| `GiftCards`, `GiftCardSellables`, `StoreCredits` | Selling at face value, issuing once per order, loading, voiding, granting per customer and currency, a load or a grant replayed under its idempotency key |
| `StoredValuePayments`, `StoredValueGateway` | Paying what the instrument can, a chosen amount, refusals that leave nothing behind, refunds back to the instrument |
| Mapping | Every column of both tables |
| `PhilCommerceStoredValueBundle` | The configuration and its bounds, the services with DoctrineBundle, the sellable alone without, the mapping, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `StoredValueFlowTest` pays an order with a gift card and store credit, then refunds part of the card's
payment. Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

An integration suite runs on a real database, through an entity manager of the module's mapping: six processes issue
the cards of one order at the same moment and one series of cards is stored; a request that counted the cards too early
finds those of the first, and leaves the caller's transaction and entity manager usable; the database refuses a rank
twice for one order, and a key twice for one instrument. It runs on a SQLite file every time, and on MySQL 8 and
PostgreSQL 17 when `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` point at them — `composer test:services` starts
them from the root of the monorepo.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-stored-value-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```
