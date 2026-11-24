# Installation

```sh
composer require phil/commerce-consignment-bundle
```

The bundle is a module of the core of commerce, on the stock of its logistics module: register the three bundles, with
DoctrineBundle, in `config/bundles.php`. Without the core or the logistics module, the container refuses to build.

```php
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle::class => ['all' => true],
```

The Flex recipe registers the bundle and writes `config/packages/phil_commerce_consignment.yaml` with the default
issue order.

The package needs PHP 8.5 or later and Symfony 8.2 or later, with `phil/commerce-bundle` 0.4 and
`phil/commerce-logistics-bundle` 0.1.1.

## The tables

The bundle maps five tables, registered with Doctrine by the bundle itself; write their migration as usual:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Table | What it holds |
|---|---|
| `phil_commerce_consignment_layer` | One row per layer: `id`, `origin` (the receipt's layer), `sku`, `location`, `consignor` (empty for the shop), `currency`, `unit_cost`, `share`, `commission_ppm`, `received_at`, `reference`, `received`, `remaining` and `version`. |
| `phil_commerce_consignment_entry` | One row per movement, never changed: `id`, `layer`, `sku`, `location`, `consignor`, `kind`, `quantity`, `reference`, `moved_at`, and for a sale, a return or a buy-out `currency`, `unit_price`, `revenue` and `cost`. |
| `phil_commerce_consignment_agreement` | One row per version of an agreement, never changed: `id`, `consignor`, `effective_from` (unique with the consignor), `term_days`, `unsold_policy`, `extension_days`, `extensions`, `frequency` and `recorded_at`. |
| `phil_commerce_consignment_statement` | One row per statement, never changed: `id`, `number` (unique), `consignor`, `currency`, `period_start`, `period_end`, `issued_at`, the units sold, returned, bought out and given back, `revenue`, `share`, `commission`, and the frozen lines and units held in `entry_lines` and `held_units`, as JSON. |
| `phil_commerce_consignment_payout` | One row per payout or reversal, never changed: `id`, `idempotency_key` (unique), `consignor`, `currency`, `amount`, `paid_at`, `method`, `reference`, `reverses` (unique: a payout is reversed once) and `recorded_at`. |

Amounts are integers in the currency's smallest unit. On MySQL and MariaDB, the keys — identifiers, SKU, location,
consignor, reference — take the binary collation `utf8mb4_bin`, so the SKU `bk-1` is never `BK-1`; PostgreSQL and
SQLite compare them byte by byte already. Every instant is a `datetime_utc_immutable`: it is written and read in UTC
whatever the time zone of the process, and comes back in UTC.

## Twig

The statement's template, `@PhilCommerceConsignment/statement.html.twig`, is found by TwigBundle in the bundle's
`templates/` directory; without Twig, the bundle works the same and the application draws statements from
`StatementView::data()` as it likes.

## Messenger

With `symfony/messenger` installed, the bundle registers the handler of `RecordShipment`, which writes to the ledger
what an order shipped: see [With the logistics module](logistics.md#shipped-orders).
