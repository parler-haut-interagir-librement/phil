# Changelog

All notable changes to `phil/commerce-catalog-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `Product` and `ProductKind`, with attributes and serial numbers on physical products; `ProductSellables`, the core's
  `SellableResolver` for the type `product`, which describes a line in the buyer's locale and never sells a family;
  `ProductSchema`: a product as a schema.org `Product` with its `Offer`, a variant with its family and its options.
- Families and variants: `Product::defineAxes()` and `Product::variant()`; `ProductVariants`, which creates a
  family's variants, finds one by its values, lists the choices of those still sold, shows a variant's attributes
  over its family's, passes a family's tax category, channels and serial numbers down to its variants
  (`cascade()`), and gives the range of their prices (`priceRange()`); `ProductFamilies`, which lists a family's
  variants. `ProductSchema` takes the attributes to show.
- Composite products, sold as one line and taxed whole: `ProductComponent`, `Product::composeOf()`;
  `CompositeProducts`, which checks the parts against the catalogue, one level deep, and reads them from an order
  line.
- `ProductTranslation`, the name in one locale, on the translations of `phil/doctrine-behaviours`; `ProductTexts` and
  `ProductTextSet`, which read a list of names in one query along a fallback chain, and the configuration
  `phil_commerce_catalog.translations.locales`.
- `ProductClassification`, products filed in categories and tags, and `TaxonomyProductClassification`, its adapter on
  `phil/taxonomy-bundle`, registered when its taxonomy is.
- The channels that sell a product, and its offer in them — `Product::offer()`, `changeOffer()`, `offerThrough()`, the
  columns `visibility`, `orderable` and `price_shown`, a variant starting at its family's —; `ProductAvailability`, the
  core's `SellableAvailability`, which answers the stricter of a product's and its family's `Offer` in the channel, for
  a product at its price, at a chosen price and as a community copy, and `of()` for a product a page holds.
- `ProductListing`, `ListedProduct` and `ProductPage`: the products a channel lists, never one left to its direct link,
  page by page by a cursor on the SKU, ascending or descending, with their names in a locale; `DbalProductListing`, its
  adapter on DBAL, which reads ten batches per page at most, tested on SQLite, MySQL and PostgreSQL.
- Product feeds through `phil/product-feeds`, suggested: the configuration `phil_commerce_catalog.product_feeds`;
  `FeedDefinition`; `CatalogProductSource`, which reads the products a channel lists, leaves out one whose price is
  hidden, writes one no order may hold out of stock, and gives the price books' lower price as the sale price, an item
  the feed cannot carry left out and logged; `ProductFeeds`, which publishes a feed by name; the command
  `phil:commerce-catalog:publish-feeds`; the message `PublishProductFeed` and its handler for Symfony Messenger, which
  a schedule of Symfony Scheduler sends; `ProductFeedController` and the route `/product-feeds/{feed}` of
  `config/routes.php`, which serve a feed's file with its media type, `Last-Modified`, an entity tag of its time and
  size, `304 Not Modified`, and the feed's `max_age`.
- `PriceBook` and `PriceTier`, per customer group, channel, period and quantity; `PriceBook::setPrice()` finds the
  tier's place by binary search in the tiers, sorted by SKU in byte order, then quantity. A book is tried after the
  books its `after` list names (`after()`, `placeAfter()`, the column `after_books`); `PriceBookOrder` sorts them
  with Symfony's `BeforeAfterSorter`, by code otherwise. `PriceBookPrices`, the core's `UnitPriceResolver`, which
  reads and orders the active books once, with their tiers indexed by SKU, until `reset()`; the `kernel.reset` tag
  on the service. `PriceBookOrderMigration`, which moves a table of books ordered by a numeric `priority` to `after`
  lists, keeping their order, tested on SQLite, MySQL and PostgreSQL. `starts_at` and `ends_at` have the DBAL type
  `datetime_utc_immutable`: a period given in any time zone is written and read back in UTC; the SQL is that of a
  `DATETIME`, so a table created before needs no migration, but periods written in another time zone than UTC are
  converted once to UTC.
- Price rules: `Product::payWhatYouWant()`, `fixPrice()`, `chosenPrices()`, and `PriceRuleSellables`, the core's
  `SellableResolver` for `pay_what_you_want:<sku>` and `community_copy:<sku>`, and its `SellableOptions` for
  `pay_what_you_want`: one required amount option, `price`, from the base price up to the product's maximum or the
  configuration's `pay_what_you_want.default_maximum`, the line priced at zero and the price chosen charged as the
  option's supplement; `reference()`, `configuration()` and `chosenPriceOf()`; a product without a maximum, or whose
  buyer no longer chooses the price, refused with `CatalogRefused`; the fuzz target `chosen-price`, on the option's
  value; community copies
  (`Product::offerCommunityCopies()`, `CommunityCopies`, `CommunityCopyFunding`, `CommunityCopyClaim`,
  `CommunityCopyLedger` and `DoctrineCommunityCopyLedger`), funded once per order through the core's
  `OrderPurchased` and claimed one per person under the product's lock; the translations of the domain
  `phil_commerce_catalog` in English and French. `funded_at` and `claimed_at` have the DBAL type
  `datetime_utc_immutable`, written and read back in UTC.
- `CatalogRefused`.
- `ProductRepository`, `PriceBookRepository` and their Doctrine adapters, sorted by `\SortDirection`;
  `DoctrineProductRepository` implements `ProductFamilies` too.
- `PhilCommerceCatalogBundle`, extension alias `phil_commerce_catalog`, which refuses to load without
  `PhilCommerceBundle`.
- The Flex recipe in `recipe/0.1`: the bundle registered and `config/routes/phil_commerce_catalog.yaml`, the routes of
  the product feeds.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
