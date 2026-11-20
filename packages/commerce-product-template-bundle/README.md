# phil/commerce-product-template-bundle

**Every SKU of a design on a template, generated in the catalogue, kept as one page, with its mockups, its options,
and the print files and production order of each order.**

phil/commerce-product-template-bundle is a module of `phil/commerce-catalog-bundle`. It stores product templates and
designs, as `phil/commerce-product-template` describes them, and **generates** the products of a design on a template
in the catalogue: one product per SKU, named, priced, classified, described by its attributes and sold through its
channels. Generating again updates what changed, restores what came back and withdraws what the plan no longer has;
nothing is deleted. The products of a design on a template form a **group**: one page with a selector per axis, each
product still a SKU of the catalogue. Their **mockups** are composed by `phil/print-compositor`, the artworks on the
photos of the blank, one WebP per colour and face. The **options** a template or a design declares — a text, a choice,
an ink, a draw — are given to the core of commerce through its `SellableOptions` port, so an order line configured
with them is checked and charged. Once an order is paid and drawn, its **print files** are composed — the artwork, the
text the buyer typed in an imposed font and zone, the objects of a loot box picked by its dice, the fixed objects of a
version, one file per ink — and its **production order** is written for the printer, at once or in a worker by a
Messenger message that carries the order's number only. A Stimulus controller chooses a product of a group, the
combinations it does not sell disabled.

It is for a shop that prints its designs on blanks in many sizes and colours: shirts are the example the
documentation follows, as they are the reason the module exists, but a template knows nothing of shirts.

It is a Symfony bundle that refuses to load without `phil/commerce-bundle` and `phil/commerce-catalog-bundle`.

## Status

| Part | State |
|---|---|
| Templates and designs stored, with their options | Implemented and tested |
| Generation in the catalogue: create, update, restore, withdraw, never delete | Implemented and tested |
| Groups: members by SKU, the page of a group, the SKU of the values chosen | Implemented and tested |
| Mockups with `phil/print-compositor`, named by their content, in a store of images | Implemented and tested |
| Options of templates and designs given to the core | Implemented and tested |
| Storage on Doctrine | Implemented and tested |
| Print files and production orders: print layouts, dice, inks, JSON and CSV, a private store | Implemented and tested |
| Production in a worker by Messenger, idempotent, on purchase if configured | Implemented and tested |
| Stimulus controller choosing a product of a group, served by AssetMapper | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the module. |
| [Configuration](doc/configuration.md) | Every key, its default and what it changes. |
| [Generation](doc/generation.md) | Storing a template and a design, generating, what changes, what is refused. |
| [Groups](doc/groups.md) | Members, the page of a group, finding a SKU. |
| [Mockups](doc/mockups.md) | The store of images, what is composed, names, running again. |
| [Options](doc/options.md) | Declaring options on templates and designs, conditions, what the core receives. |
| [Print files and production orders](doc/production.md) | Print layouts, dice, print files, the production order, Messenger, the production store. |
| [Choosing a product](doc/selector.md) | The Stimulus controller of a group's page. |
| [Storage](doc/storage.md) | Tables, columns and JSON. |
| [Extension points](doc/extension-points.md) | Ports, services and what an application replaces. |
| [Testing](doc/testing.md) | The module's tests, and testing an application that generates. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-product-template-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle::class => ['all' => true],
];
```

```php
$templates->add(ProductTemplate::create($shirt, ProductKind::Physical, 'goods'));
$designs->add(ProductDesign::create($gnome));
$entityManager->flush();

$report = $generation->generate('NAQ001', 'unisex');   // NAQ001FOMBK, NAQ001FOMPK, ...
$entityManager->flush();

// Once an order is paid and its draws are made, in a worker:
$bus->dispatch(new ProducePrintFiles($order->number));
```

## Requirements

PHP 8.5 or later with `ext-sodium`; `phil/commerce-bundle`, `phil/commerce-catalog-bundle`,
`phil/commerce-product-template`, `phil/ddd-bundle`, `phil/money` and `phil/print-compositor` 0.1; Doctrine ORM 3.7;
`symfony/config` and `symfony/dependency-injection` 8.2 or later. `symfony/messenger` 8.2 to produce in a worker;
AssetMapper and `symfony/stimulus-bundle` for the controller.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
