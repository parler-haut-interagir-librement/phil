# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `ext-mbstring` | any | The length of labels, in characters |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: `OrderProcessor`, `Order`, `Adjustment` |
| `phil/commerce-catalog-bundle` | ^0.1@dev | The products promotions discount, under the reference type `product` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/doctrine-behaviours` | ^0.2@dev | The translations of the labels |
| `phil/locale-dimensions` | ^0.1@dev | The locales served and their fallback chains |
| `phil/money` | ^0.1@dev | Amounts off, bundle prices and minimums |
| `doctrine/orm` | ^3.7 | The mapping attributes and the repository adapter |
| `psr/clock` | ^1.0 | The moment promotions are checked at; FrameworkBundle provides the clock with `symfony/clock` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class is an `AbstractBundle` with a configuration tree |
| `symfony/translation-contracts` | ^3.6 | The request's locale, read from the translator |

`phil/commerce-logistics-bundle` is suggested: it computes the delivery charges a free shipping waives.

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-promotions-bundle
```

## Registering

With Symfony Flex, the recipe of each package registers its bundle; the module's defaults need no configuration file.
Without Flex:

Register the core, the catalogue and the module; the order of the lines does not matter.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle::class => ['all' => true],
];
```

Registered without the core or the catalogue, the module fails the container build:

```text
PhilCommercePromotionsBundle is a module of the core of commerce on its catalogue: register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle, from phil/commerce-catalog-bundle, too.
```

With DoctrineBundle, generate a migration for its tables `phil_commerce_promotion`,
`phil_commerce_promotion_translation`, `phil_commerce_promotion_code`, `phil_commerce_promotion_redemption` and
`phil_commerce_promotion_customer_use`. Register `TranslationListener` of `phil/doctrine-behaviours` — the
`phil/doctrine-behaviours-bundle` does — so that the translations keep one row per promotion and locale.

Every service of the module reads the repository: with DoctrineBundle, the module registers them with its Doctrine
adapter; without it, it registers nothing, and the application declares the services with its own adapter.
