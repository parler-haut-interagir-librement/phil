# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `ext-mbstring` | any | Counts the characters of holders and notes |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: its orders, payments, `SellableResolver` and `PaymentGateway` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | Balances and movements |
| `phil/paged-media` | ^0.1@dev | The printed gift card |
| `doctrine/orm` | ^3.7 | The mapping attributes of instruments and movements, and the repository adapter |
| `doctrine/dbal` | ^4.5 | The unique constraint violation the adapter catches when another request issued an order's cards first |
| `doctrine/event-manager` | ^2.1 | The application's Doctrine listeners, given to the entity manager that stores an order's cards |
| `psr/clock` | ^1.0 | The moment a refund gives value back |
| `symfony/uid` | ^8.2 | Instrument and movement identifiers |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration tree |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-stored-value-bundle
```

## Registering

With Symfony Flex, the recipe of each package registers its bundle; the module's defaults need no configuration file.
Without Flex:

Register the core and the module; the order of the two lines does not matter.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceStoredValueBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configuring

Every key is optional.

```yaml
# config/packages/phil_commerce_stored_value.yaml
phil_commerce_stored_value:
    gift_card:
        label: 'Gift card'        # how a gift card line reads, before its value
        tax_category: exempt      # selling a gift card is, in most places, not taxed
        max_value: 100000         # the highest value sold, in minor units
```

Give the core a tax rate for the category, at zero where the sale of a gift card is not taxed.

## Storage

The services read a repository: with DoctrineBundle, the module registers them with its Doctrine adapter — generate a
migration for its two tables —; without it, only the gift card sellable is registered, and the application declares
the rest with its own adapter.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

The module ships no migration: the application generates it from the mapping, as for its own entities.

### Upgrading from 0.1

The movements table gains two nullable columns and two unique constraints, which keep value from being created twice
([Value created once](stored-value.md#value-created-once)): `issued_unit`, the rank of a card among the cards its order
issued, and `idempotency_key`. `doctrine:migrations:diff` writes the migration; it amounts to the following.

MySQL and MariaDB:

```sql
ALTER TABLE phil_commerce_stored_value_movement
    ADD issued_unit INT DEFAULT NULL,
    ADD idempotency_key VARCHAR(128) DEFAULT NULL;
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_issued_unit
    ON phil_commerce_stored_value_movement (order_number, kind, issued_unit);
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_idempotency_key
    ON phil_commerce_stored_value_movement (instrument, idempotency_key);
```

PostgreSQL:

```sql
ALTER TABLE phil_commerce_stored_value_movement ADD issued_unit INT DEFAULT NULL;
ALTER TABLE phil_commerce_stored_value_movement ADD idempotency_key VARCHAR(128) DEFAULT NULL;
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_issued_unit
    ON phil_commerce_stored_value_movement (order_number, kind, issued_unit);
CREATE UNIQUE INDEX phil_commerce_stored_value_movement_idempotency_key
    ON phil_commerce_stored_value_movement (instrument, idempotency_key);
```

The existing rows keep both columns empty. A unique index lets any number of rows leave a column of it empty, on MySQL,
MariaDB, PostgreSQL and SQLite alike, so cards issued before the upgrade, and movements without a key, never collide;
the cards an order issues after it take the ranks that follow those it already had.
