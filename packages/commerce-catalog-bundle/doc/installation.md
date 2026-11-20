# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `ext-mbstring` | any | The length of names and attribute values, in characters |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: `SellableResolver`, `UnitPriceResolver`, `SellableAvailability`, `OrderPurchased` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/doctrine-behaviours` | ^0.2@dev | The translations of product names |
| `phil/locale-dimensions` | ^0.1@dev | The locales served and their fallback chains |
| `phil/money` | ^0.1@dev | Base prices, price book prices and price rules |
| `doctrine/orm` | ^3.7 | The mapping attributes of the products, price books and community copies, and the repository adapters |
| `doctrine/dbal` | ^4.5 | `PriceBookOrderMigration`, which alters the table of price books, and `DbalProductListing` |
| `psr/clock` | ^1.0 | The moment a feed reads the price books at |
| `psr/log` | ^3.0 | The products a feed leaves out, and the feeds published |
| `symfony/polyfill-php86` | ^1.37 | `\SortDirection` on PHP 8.5, for the repositories and the lists |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class is an `AbstractBundle` with a configuration tree; `BeforeAfterSorter` orders the price books |
| `symfony/translation-contracts` | ^3.6 | The request's locale, read from the translator, and the description of a community copy |

`phil/taxonomy-bundle` is suggested: with its taxonomy registered, products are filed in its categories and tags.
`phil/product-feeds` is suggested: with it, the catalogue is published as feed files; `symfony/messenger` publishes
them by a message, `symfony/console` gives their command, and `symfony/http-foundation` the controller of the route
that serves their files ([Product feeds](product-feeds.md#serving)).
`phil/commerce-promotions-bundle` is the module of promotions on these products.

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle
```

## Registering

With Symfony Flex, the recipe of each package registers its bundle; the module's recipe also adds
`config/routes/phil_commerce_catalog.yaml`, the routes of the product feeds. Without Flex:

Register the core and the module; the order of the two lines does not matter.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceCatalogBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

The module's configuration keys are the locales product names are translated in and the product feeds: see
[Configuration](configuration.md). With DoctrineBundle, generate a migration for its tables
`phil_commerce_catalog_product` — its columns `visibility`, `orderable` and `price_shown` included —, `phil_commerce_catalog_product_translation`,
`phil_commerce_catalog_price_book`, `phil_commerce_catalog_community_copy_funding` and
`phil_commerce_catalog_community_copy_claim` — a table of price books ordered by number moves with
`PriceBookOrderMigration`, see [Price books](price-books.md) —, and register `TranslationListener` of
`phil/doctrine-behaviours` — the `phil/doctrine-behaviours-bundle` does — so that the translations keep one row per
product and locale.

Every service of the module reads a repository: with DoctrineBundle, the module registers them with its Doctrine
adapters; without it, it registers nothing, and the application declares the services with its own adapters.
