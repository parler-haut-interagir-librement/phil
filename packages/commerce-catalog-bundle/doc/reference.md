# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceCatalogBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_catalog`, with `translations.locales` and `product_feeds`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\Product` | `final class`, Doctrine entity, `Translatable` | A product: SKU, kind, name, base price, tax category, attributes, offer; a family and its axes, a variant and its values, a composite and its parts; serial numbers; its price rules; its suggested retail price |
| `Domain\Model\ProductTranslation` | `final class`, Doctrine entity, `Translation` | A product's name in one locale |
| `Domain\ValueObject\ProductComponent` | `final readonly class` | A part of a composite product: a SKU and its units |
| `Domain\Product\ProductKind` | string-backed enum | Physical, digital, service |
| `Domain\Model\PriceBook` | `final class`, Doctrine entity | Unit prices per group, channel, period and quantity; the books it is tried after |
| `Domain\Model\CommunityCopyFunding` | `final class`, Doctrine entity | What one order gave towards one product's community copies |
| `Domain\Model\CommunityCopyClaim` | `final class`, Doctrine entity | A community copy claimed by one person |
| `Domain\ValueObject\PriceTier` | `final readonly class` | The unit price of a SKU from a quantity |
| `Domain\Exception\CatalogRefused` | `final class`, `DomainError` | A price in another currency, a SKU taken, a variant's values taken, a part unknown or nested, a category unknown, a community copy not offered, none left or already claimed |
| `Domain\Repository\ProductRepository` | interface | Finds and stores products |
| `Application\Contract\ProductFamilies` | interface | Lists a family's variants |
| `Domain\Repository\PriceBookRepository` | interface | Finds and stores price books; the active ones by code |
| `Application\Contract\CommunityCopyLedger` | interface | Keeps the funding and the claims of community copies, and locks a product for a claim |
| `Application\ProductSellables` | `final readonly class`, the core's `SellableResolver` | Products sold under the type `product`, described in the buyer's locale; families never |
| `Application\ProductVariants` | `final readonly class` | A family's variants: created, found by their values, the choices they offer, their attributes with the family's, what the family passes down, their range of prices |
| `Application\PriceRuleSellables` | `final readonly class`, the core's `SellableResolver` and `SellableOptions` | A price the buyer chose, under `pay_what_you_want`, as the amount option `price`, and a community copy, under `community_copy` |
| `Application\CommunityCopies` | `final readonly class`, a listener of the core's `OrderPurchased` | Community copies funded by paid orders, counted and claimed |
| `Application\CompositeProducts` | `final readonly class` | Composite products: their parts checked, and read from an order line |
| `Application\CompositeCosts` | `final readonly class`, `ProductCosts` | The cost of a product, a composite's the sum of its parts' |
| `Application\ProductTexts` | `final readonly class` | Product names in a locale, from stored translations; writes a translation |
| `Application\ProductTextSet` | `final readonly class` | The names read for a list of products |
| `Application\Contract\ProductClassification` | interface | Products filed in categories and tags |
| `Application\ProductAvailability` | `final readonly class`, the core's `SellableAvailability` | The channels' selections and the offers of products, a variant's made stricter by its family's; `of()` |
| `Application\Contract\ProductListing` | interface | The products a channel shows, page by page |
| `Application\Contract\ProductCosts` | interface | What one unit of a product costs the shop |
| `Application\Contract\UnitCosts` | interface | What one unit of a SKU costs, from whatever values the stock |
| `Application\ListedProduct` | `final readonly class` | A product as a list shows it, read without its entity; `isFamily()`, `value()`, `named()` |
| `Application\ProductPage` | `final readonly class` | A page of products and the cursor of the next |
| `Application\PriceBookPrices` | `final class`, the core's `UnitPriceResolver`, tagged `kernel.reset` | The price books' prices, the active books read and ordered once until `reset()` |
| `Application\PriceBookOrder` | `final class` | The order price books are tried in, from their `after` lists |
| `Application\ProductSchema` | `final class` | A product as a schema.org `Product` with its `Offer`, a variant with its family, the attributes given |
| `Infrastructure\Taxonomy\TaxonomyProductClassification` | `final readonly class` | `ProductClassification` on `phil/taxonomy-bundle`, a product being the resource `phil_commerce_product` |
| `Infrastructure\Doctrine\DoctrineProductRepository` | `final readonly class` | Products and the variants of a family through the entity manager |
| `Infrastructure\Doctrine\DoctrinePriceBookRepository` | `final readonly class` | Price books through the entity manager |
| `Infrastructure\Doctrine\DoctrineCommunityCopyLedger` | `final readonly class` | Community copies through the entity manager, summed and counted in SQL |
| `Infrastructure\Doctrine\PriceBookOrderMigration` | `final class` | Moves a table of books ordered by `priority` to `after` lists, from an application's migration |
| `Infrastructure\Doctrine\DbalProductListing` | `final readonly class` | `ProductListing` on DBAL, by a cursor on the SKU |
| `Infrastructure\Logistics\LogisticsUnitCosts` | `final readonly class`, `UnitCosts` | The unit costs of `phil/commerce-logistics-bundle`'s costing |
| `Infrastructure\ProductFeeds\FeedDefinition` | `final readonly class` | One feed of the configuration; `address()` fills a template |
| `Infrastructure\ProductFeeds\CatalogProductSource` | `final readonly class`, the `ProductSource` of `phil/product-feeds` | The products a channel lists with their price, as the items of a feed |
| `Infrastructure\ProductFeeds\ProductFeeds` | `final readonly class` | Publishes the feeds of the configuration by name; `names()`, `definition()`, `publish()` |
| `Infrastructure\ProductFeeds\PublishProductFeed` | `final readonly class` | The message that asks for a feed, by its name |
| `Infrastructure\ProductFeeds\PublishProductFeedHandler` | `final readonly class` | Publishes the feed a message names |
| `Infrastructure\ProductFeeds\PublishProductFeedCommand` | `final readonly class`, an invokable command | `phil:commerce-catalog:publish-feeds [feed…]` |
| `Infrastructure\Http\ProductFeedController` | `final readonly class`, a controller | Serves a feed's file at `/product-feeds/{feed}`, with its cache headers |

See [scope.md](scope.md) for what is not started.
