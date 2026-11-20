# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `psr/clock` | The moment counted, from the application's clock |
| `doctrine/dbal` 4.5, optional | `Dbal\DbalUsageStore`, the counters in a database |
| `doctrine/orm` 3.7, optional | `Dbal\UsageSchemaListener`, the tables in the schema the ORM generates |
| `phil/commerce-subscription-bundle`, optional | `Subscription\FeatureLimits::fromSubscriptions()`, limits from subscription features |

## Installing

```sh
composer require phil/usage-meter
```

It is a library, not a bundle: there is nothing to register. Build one `UsageMeter` with the application's meters, a
store and a PSR-20 clock — Symfony's `clock` service, or any other —, and keep it; it holds no state of its own.

## In a Symfony application

`phil/usage-meter-bundle` declares everything below from its configuration, with rate limiters on controllers and a
console purge. Without the bundle, declare the meters, the store and the meter as services. With Doctrine, the store
uses the application's DBAL connection and the listener adds its tables to the schema of that connection, so that
`doctrine:migrations:diff` writes the migration that creates them:

```yaml
services:
    app.meter.exports:
        class: Phil\UsageMeter\Meter
        arguments:
            $name: exports
            $period: !service { class: Phil\UsageMeter\Period\CalendarPeriod, factory: ['Phil\UsageMeter\Period\CalendarPeriod', 'month'], arguments: ['America/Toronto'] }
            $limit: !service { class: Phil\UsageMeter\Limit, factory: ['Phil\UsageMeter\Limit', 'of'], arguments: [3] }

    Phil\UsageMeter\Meters:
        arguments: ['@app.meter.exports']

    Phil\UsageMeter\Dbal\DbalUsageStore:
        arguments: ['@doctrine.dbal.default_connection']

    Phil\UsageMeter\Dbal\UsageSchemaListener:
        arguments: ['@doctrine.dbal.default_connection', '@Phil\UsageMeter\Dbal\DbalUsageStore']
        tags:
            - { name: doctrine.event_listener, event: postGenerateSchema }

    Phil\UsageMeter\UsageMeter:
        arguments:
            $meters: '@Phil\UsageMeter\Meters'
            $store: '@Phil\UsageMeter\Dbal\DbalUsageStore'
            $clock: '@clock'
            $limits: '@app.usage_limits'
```

With `phil/commerce-subscription-bundle`, the limits come from the subscriber's offers:

```yaml
services:
    app.usage_limits:
        class: Phil\UsageMeter\Subscription\FeatureLimits
        factory: ['Phil\UsageMeter\Subscription\FeatureLimits', 'fromSubscriptions']
        arguments: ['@Phil\CommerceSubscriptionBundle\Application\FeatureAccess']
```

A scheduled task calls `UsageMeter::purge()` — daily is enough — to forget the counters and keys that no window counts
any more.
