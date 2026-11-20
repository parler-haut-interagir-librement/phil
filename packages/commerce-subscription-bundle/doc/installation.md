# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: its orders, `OrderModifier` and `SellableResolver` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | The price of an offer |
| `doctrine/orm` | ^3.7 | The mapping attributes of `Offer`, `Subscription`, `Gift` and `Seat`, and the repository adapters |
| `symfony/uid` | ^8.2 | The time-ordered UUID that names a subscription and a gift |
| `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle` |
| `symfony/config` | ^8.2 | The configuration tree |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-subscription-bundle
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
    Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceSubscriptionBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

The module's keys are described in [configuration.md](configuration.md). With DoctrineBundle, generate a migration
for its four tables.

Every service of the module reads a repository: with DoctrineBundle, the module registers them with its Doctrine
adapters; without it, it registers nothing, and the application declares the services with its own adapters.
