# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application extends or replaces. The ports of the
core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag and order |
|---|---|---|
| `SellableResolver` | `ProductSellables`, for the reference type `product` | `phil_commerce.sellable_resolver` |
| `SellableResolver` | `PriceRuleSellables`, for the reference types `pay_what_you_want` and `community_copy` | `phil_commerce.sellable_resolver` |
| `SellableOptions` | `PriceRuleSellables`, the amount option `price` of the reference type `pay_what_you_want` | `phil_commerce.sellable_options` |
| `UnitPriceResolver` | `PriceBookPrices` | `phil_commerce.unit_price_resolver` |
| `SellableAvailability` | `ProductAvailability`, the product's `Offer` in the channel | `phil_commerce.sellable_availability` |

An application's own resolver or processor of the same port is tagged by the core's autoconfiguration, and names
these services in its tag's `before` or `after` to take its place in the order: a price of its own before or
after the price books. See [Scope](scope.md).

## Repositories

Each repository port is a public alias of the module's Doctrine adapter; an application points it at a service of
its own under the same contract.

| Port | Adapter |
|---|---|
| `ProductRepository` | `DoctrineProductRepository` |
| `ProductFamilies` | `DoctrineProductRepository` |
| `PriceBookRepository` | `DoctrinePriceBookRepository` |
| `Application\Contract\CommunityCopyLedger` | `DoctrineCommunityCopyLedger` |

## Ports it offers

| Port | Adapter |
|---|---|
| `Application\Contract\ProductClassification` | `TaxonomyProductClassification`, on `phil/taxonomy-bundle`, registered when its taxonomy is; an application aliases the port to its own service otherwise |
| `Application\Contract\ProductListing` | `DbalProductListing`, a public alias; an application that stores its products otherwise points it at its own service — the feeds read it too |
| `ProductSource` of `phil/product-feeds` | `CatalogProductSource`, one per feed, built by `ProductFeeds` |
| `Application\Contract\ProductCosts` | `CompositeCosts`, a public alias: the cost of one unit of a product, a composite's the sum of its parts' — see [Variants and composites](variants-and-composites.md#the-cost-of-a-composite) |
| `Application\Contract\UnitCosts` | `LogisticsUnitCosts`, on the costing of `phil/commerce-logistics-bundle`, registered when its bundle is; an application aliases the port to its own service otherwise |

## Services

| Service | How an application relies on it |
|---|---|
| `ProductVariants` | Creates a family's variants, finds one by its values, lists the choices; public — see [Variants and composites](variants-and-composites.md) |
| `CompositeProducts` | Makes a product a composite of others, and reads the parts of an order line; public |
| `ProductTexts` | Reads product names in a locale and writes their translations; public — see [Names and classification](names-and-classification.md) |
| `ProductFeeds` | Publishes a feed of the configuration by name; public — see [Product feeds](product-feeds.md) |
| `PublishProductFeedHandler` | Publishes the feed a `PublishProductFeed` names; tagged `messenger.message_handler` when Messenger is installed |
| `PublishProductFeedCommand` | `phil:commerce-catalog:publish-feeds`; tagged `console.command` when Console is installed |
| `CommunityCopies` | Counts and gives community copies; listens to the core's `OrderPurchased` through the tag `kernel.event_listener`; public — see [Price rules](price-rules.md) |

## The order of price books

A price book's `after` list places it after other books: an application adds a book between two others by naming
them, and `PriceBookOrder` sorts them as Symfony sorts tagged services. A price resolver of the application's own is
placed among the core's resolvers by its tag's `before` and `after`, as above.

## Translations

The description of a community copy's line is the key `phil_commerce_catalog.community_copy` of the domain
`phil_commerce_catalog`, with the parameter `%name%`, and the label of a chosen price's option the key
`phil_commerce_catalog.chosen_price`, `Your price`; both are translated in `translations/phil_commerce_catalog.en.php`
and `.fr.php`, and an application overrides them in its own translations.
