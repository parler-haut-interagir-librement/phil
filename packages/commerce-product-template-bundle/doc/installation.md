# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `ext-sodium` | The random name of the temporary file a mockup is written to |
| `phil/commerce-bundle` | The core: orders, their configured lines and the `SellableOptions` port |
| `phil/commerce-catalog-bundle` | The catalogue the products are generated in |
| `phil/commerce-product-template` | Templates, designs and the planner |
| `phil/print-compositor` | Mockups, drawn with GD |
| `phil/ddd-bundle`, `phil/money` | Domain errors and amounts |
| Doctrine ORM 3.7, DoctrineBundle | Storage of templates, designs and groups |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-product-template-bundle
```

With Symfony Flex, the recipe of each package registers its bundle; the module's defaults need no configuration file.
Without Flex:

Register the three bundles; the module refuses to load without the core and the catalogue:

```php
// config/bundles.php
return [
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle::class => ['all' => true],
];
```

With DoctrineBundle, the module maps its four entities; create their tables with the application's migrations
([Storage](storage.md)). Without DoctrineBundle, only the planner is registered, and the application declares the
other services with its own repositories ([Extension points](extension-points.md)).
