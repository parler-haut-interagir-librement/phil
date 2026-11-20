# Scope

The catalogue for the core of commerce: products and their attributes, families and variants, composite products,
translated names, categories and tags, price books and price rules. Promotions on its products are the module
`phil/commerce-promotions-bundle`.

| Part | Content | State |
|---|---|---|
| Products | Physical, digital and service products with their attributes, sold through the core's `SellableResolver` under the reference type `product` | Implemented and tested ([products.md](products.md)) |
| Selections and offers | The channels that sell each product, and what they offer of it — listed, by direct link only or hidden; orderable or not; priced or not —, a variant at the stricter of its own offer and its family's, as the core's `SellableAvailability`; the cart refuses what is not orderable | Implemented and tested ([products.md](products.md#offers)) |
| Lists | The products a channel shows, page by page on DBAL, by a cursor on the SKU, with their names in a locale | Implemented and tested ([products.md](products.md#lists-of-products)) |
| Product feeds | The catalogue published as RSS or TSV feed files through `phil/product-feeds`, by a command or a message a schedule sends, and served by a route with cache headers | Implemented and tested ([product-feeds.md](product-feeds.md)); pushing to a marketplace's API is a later step |
| Price books | Prices per customer group, channel — a site, a point of sale —, period and quantity tier, ordered by their `after` lists, as the core's `UnitPriceResolver`; a migration from books ordered by number | Implemented and tested ([price-books.md](price-books.md)) |
| Variants | Families that name their axes, variants that give each axis one value and take their family's attributes, tax category, channels and serial numbers | Implemented and tested ([variants-and-composites.md](variants-and-composites.md)) |
| Composite products | A product made of others, sold as one line and taxed whole | Implemented and tested ([variants-and-composites.md](variants-and-composites.md)) |
| Names | Product names translated, read along a fallback chain | Implemented and tested ([names-and-classification.md](names-and-classification.md)) |
| Taxonomy | Categories and tags, through `phil/taxonomy-bundle` | Implemented and tested ([names-and-classification.md](names-and-classification.md)) |
| Price rules | Pay what you want from the base price up to a maximum — the product's or the site's default —, with a suggested price, through the core's amount option; community copies funded by paid orders and claimed one per person | Implemented and tested ([price-rules.md](price-rules.md)) |
| Serial numbers | Which products carry one; the numbers themselves are kept with the stock of the logistics module | Implemented and tested ([products.md](products.md)) |
| Reviews | Reviews of a product, published after moderation | Not started: they come from `phil/reviews-bundle`, which holds only its skeleton; the catalogue shows none until it is written |
| Products a buyer assembles | A product built from the buyer's choices | Not started: it is planned after this release, with the other parts that follow the catalogue |
| A range of prices in words | A family's lowest and highest price written as one range in the buyer's locale | Not started: `priceRange()` gives both prices; writing them as one range waits for a range filter of `phil/money-bundle` |

## The ports of the core it implements

- `SellableResolver`, for the reference type `product`: `ProductSellables`; for `pay_what_you_want` and
  `community_copy`: `PriceRuleSellables`.
- `SellableOptions`, for the reference type `pay_what_you_want`: `PriceRuleSellables`, which declares the amount
  option of a chosen price.
- `UnitPriceResolver`: `PriceBookPrices`.
- `SellableAvailability`: `ProductAvailability`, which answers the product's `Offer` in the channel.

## The library it feeds

- `phil/product-feeds`, suggested: `CatalogProductSource` is its `ProductSource`, and `ProductFeeds` publishes the
  feeds the configuration declares with its `FeedPublisher`.

## The core's events it listens to

- `OrderPurchased`: `CommunityCopies`, which records what a paid order gave towards community copies.
