# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `ext-mbstring` | any | Counts the characters of a credit account's buyer name |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: its orders and `UnitPriceResolver` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | Minimum orders and group prices |
| `doctrine/orm` | ^3.7 | The mapping attributes of the groups and memberships, and the repository adapter |
| `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle` |
| `symfony/config` | ^8.2 | Needed by `AbstractBundle` when the module is installed on its own |
| `symfony/uid` | ^8.2 | Receivable identifiers |
| `symfony/validator` | ^8.2 | The constraint `AllowedQuantity`, checked by the validator in forms and in the cart |

Optional: `phil/commerce-logistics-bundle` 0.3 or later, whose shipments a buyer on credit then receives before paying
them ([Sales on credit](credit.md#shipping-before-the-payment)).

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-wholesale-bundle
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
    Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceWholesaleBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

The module has no configuration key. Its services read a repository: with DoctrineBundle, the module registers them
with its Doctrine adapter — generate a migration for its five tables —; without it, the application declares them
with its own adapter. The quantity rules need FrameworkBundle's validator, and its translator for their messages in
the buyer's language.
