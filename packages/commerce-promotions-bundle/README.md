# phil/commerce-promotions-bundle

**Promotions for a store built on phil/commerce-bundle and its catalogue: a share off, an amount off, N for X, bundle
prices and free shipping.**

phil/commerce-promotions-bundle is the promotions module of `phil/commerce-bundle`, on the products of
`phil/commerce-catalog-bundle`. A promotion gives a share off each eligible line, an amount off spread over them, N
units for the price of X, a price for each complete bundle of product groups, or the delivery charge waived. It
applies per channel, customer group, period, products and the products it excludes, code, uses in all and per
customer, and products the customer already owns. Promotions apply in the order their `after` lists set, and never
combine with those they are incompatible with. Labels are translated; with DoctrineBundle, all of it is stored.

It is for a store that sells from a catalogue and runs sales, bundles and coupons. Codes used without the catalogue —
donations, tickets, memberships — are another package's.

It is a Symfony bundle that refuses to load without the core and the catalogue. Installing it is enabling it.

It plugs into the core's ports and nothing else: promotions run as an `OrderProcessor` ahead of the core's taxes, and
free shipping as a second one after the processor that computes the delivery charge.

## Status

| Part | State |
|---|---|
| Share off, amount off, N for X | Implemented and tested |
| Bundle prices, one line per product | Implemented and tested |
| Free shipping, per delivery method | Implemented and tested |
| Exclusions, incompatibilities, order by `after` | Implemented and tested |
| Uses per customer, products owned | Implemented and tested |
| Codes in batches, uses counted once per order | Implemented and tested |
| Translated labels | Implemented and tested |
| Storage on Doctrine | Implemented and tested |
| Buy X get Y across two lists, campaigns with a budget, conditions from a rules package | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the module beside the core and the catalogue. |
| [Configuration](doc/configuration.md) | The locales labels are translated in. |
| [Promotions](doc/promotions.md) | What a promotion gives, when it applies, how promotions add up, codes and uses, storage. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests and benchmarks. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-promotions-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/commerce-catalog-bundle`, `phil/ddd-bundle`,
`phil/doctrine-behaviours`, `phil/locale-dimensions` and `phil/money` 0.1; Doctrine ORM 3.7; `psr/clock` 1.0, whose
clock the application provides; `symfony/config` and `symfony/dependency-injection` 8.2 or later;
`symfony/translation-contracts` 3.6 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
