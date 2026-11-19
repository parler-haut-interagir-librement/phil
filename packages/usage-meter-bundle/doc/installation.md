# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | As `phil/usage-meter` |
| `phil/usage-meter` | The meters, the periods, the stores and `UsageMeter` |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and the services |
| `phil/rate-limit-bundle` | The rules that name the rate limiters, and the 429 answer |
| `symfony/http-foundation`, `symfony/http-kernel` 8.2 | The requests the rate limiters count, the 400 answer of a malformed `Idempotency-Key` |
| `symfony/console` 8.2 | `phil:usage-meter:purge` |
| `psr/clock` | The moment counted, from the application's clock |
| `doctrine/dbal` 4.5, optional | The store `dbal`, the default |
| `doctrine/orm` 3.7, optional | The counter tables in the schema the ORM generates |
| `phil/commerce-subscription-bundle`, optional | Limits read from the features of a subscriber's offers |

## Installing

```sh
composer require phil/usage-meter-bundle
```

With Symfony Flex, the recipe registers the bundle and writes `config/packages/phil_usage_meter.yaml`, which keeps the
counters on the `default` DBAL connection and declares no meter yet; Flex enables `phil/rate-limit-bundle` as well. The
recipe lives in the recipes repository of PHIL, read once the application lists that repository in
`extra.symfony.endpoint`. Without it, add the bundles to `config/bundles.php`, `phil/rate-limit-bundle`'s too when rate
limiters are declared:

```php
return [
    // ...
    Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
    Phil\UsageMeterBundle\PhilUsageMeterBundle::class => ['all' => true],
];
```

The bundle reads Symfony's `clock` service, which FrameworkBundle registers, and the `kernel.secret` parameter for
the hashes of the idempotency keys.

## The tables

The default store keeps the counters in two tables of the `default` DBAL connection, `phil_usage_counter` and
`phil_usage_key` ([Configuration](configuration.md) renames them or picks another connection). With the ORM, the
bundle adds them to the schema it generates for that connection, so the usual commands handle them:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

`doctrine:schema:validate` then keeps them in step. Without the ORM, a migration builds them from
`DbalUsageStore::configureSchema()`, as the storage documentation of `phil/usage-meter` shows.

The DBAL store runs on PostgreSQL, MySQL, MariaDB and SQLite. `store: memory` needs no table: the counters last as
long as the process, which suits tests and commands.
