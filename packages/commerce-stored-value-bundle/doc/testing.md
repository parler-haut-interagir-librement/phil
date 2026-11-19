# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Instrument`, `Movement`, `MovementKind`, `GiftCardCode` | Every rule, expiry, voiding, the history of a balance, reading and generating codes |
| `GiftCards`, `GiftCardSellables`, `StoreCredits` | Selling at face value, issuing once per order, loading, voiding, granting per customer and currency |
| `StoredValuePayments`, `StoredValueGateway` | Paying what the instrument can, a chosen amount, refusals that leave nothing behind, refunds back to the instrument |
| Mapping | Every column of both tables |
| `PhilCommerceStoredValueBundle` | The configuration and its bounds, the services with DoctrineBundle, the sellable alone without, the mapping, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `StoredValueFlowTest` pays an order with a gift card and store credit, then refunds part of the card's
payment. Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-stored-value-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```
