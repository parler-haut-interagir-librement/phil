# phil/commerce-catalog-bundle

**Products, variants, composite products and price books for a store built on phil/commerce-bundle.**

phil/commerce-catalog-bundle is the catalogue module of `phil/commerce-bundle`. It holds products, physical, digital or
services, with their attributes, and the channels that sell each one. A family names the axes its variants differ by,
and each variant is a product of its own; a composite product is made of others and sold as one line, taxed whole.
Product names are translated, and products are filed in the categories and tags of `phil/taxonomy-bundle`. It prices
products with price books per customer group, channel, period and quantity tier, each book tried after the books its
`after` list names. A buyer may choose the price, from a minimum up, and paid orders fund community copies for people
who cannot pay. Physical products may carry serial numbers, kept with the stock of the logistics module. Each product is
listed, reached by its direct link only, or hidden, in each channel that sells it, orderable or not — announced, sold
out for good —, its price shown or not; the pages and the cart read the same answer. Long lists of products are read
page by page on DBAL, and with `phil/product-feeds` the catalogue is published as feed files for comparison sites and
marketplaces. With DoctrineBundle, all of it is stored. Promotions are `phil/commerce-promotions-bundle`; reviews and
products a buyer assembles from choices are not held here.

It is for an application that sells goods or services from a catalogue. The core holds orders, their adjustments and
taxes; an application that sells access needs the core and perhaps `phil/commerce-subscription-bundle`, and never
this module.

It is a Symfony bundle that refuses to load without the core. Installing it is enabling it: there is no list of
modules to keep.

It plugs into the core's ports and events and nothing else. Products answer `SellableResolver` under the reference
type `product`, price rules under `pay_what_you_want` — with the core's amount option, `SellableOptions` — and
`community_copy`, price books answer `UnitPriceResolver`,
selections and offers answer `SellableAvailability`, and community copies listen to `OrderPurchased`.

## Status

| Part | State |
|---|---|
| Products and attributes | Implemented and tested |
| Families and variants, what a variant takes from its family | Implemented and tested |
| Composite products | Implemented and tested |
| Translated names | Implemented and tested |
| Categories and tags, through `phil/taxonomy-bundle` | Implemented and tested |
| Price books ordered by `after` lists, and the migration from books ordered by number | Implemented and tested |
| Price rules: pay what you want, community copies | Implemented and tested |
| Serial numbers | Implemented and tested |
| Storage on Doctrine | Implemented and tested |
| Offers — listed, direct link only or hidden; orderable or not; priced or not — in the catalogue, the pages and the cart | Implemented and tested |
| Lists of the products a channel shows, page by page on DBAL | Implemented and tested |
| Product feeds through `phil/product-feeds`: the catalogue as RSS or TSV files, by a command or a scheduled message, served by a route with cache headers | Implemented and tested |
| Reviews | Not started: they come from `phil/reviews-bundle`, which holds only its skeleton |
| Products a buyer assembles from choices | Not started: planned after this release |
| A range of prices in words | Not started: it waits for a range filter of `phil/money-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the module beside the core. |
| [Configuration](doc/configuration.md) | The locales product names are translated in, and the product feeds. |
| [Products](doc/products.md) | Products, kinds, attributes, offers, lists of products, selling through the core, storage. |
| [Product feeds](doc/product-feeds.md) | The catalogue published as feed files, the fields read, the command, the message, the schedule and the route that serves them. |
| [Variants and composites](doc/variants-and-composites.md) | Families and their variants, composite products and their parts. |
| [Names and classification](doc/names-and-classification.md) | Translated names, categories and tags. |
| [Price books](doc/price-books.md) | Conditions, tiers, the order of the books, which price a line takes, storage, the migration from books ordered by number. |
| [Price rules](doc/price-rules.md) | Pay what you want, community copies, their storage. |
| [Scope](doc/scope.md) | What the module holds, what it does not yet, and the ports of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/ddd-bundle`, `phil/doctrine-behaviours`,
`phil/locale-dimensions` and `phil/money` 0.1; Doctrine ORM 3.7 and DBAL 4.5; `symfony/config` and
`symfony/dependency-injection` 8.2 or later; `symfony/translation-contracts` 3.6 or later; `psr/clock`, `psr/log` and
`symfony/polyfill-php86`. `phil/taxonomy-bundle`, `phil/product-feeds`, `symfony/messenger` and
`symfony/http-foundation` are suggested.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
