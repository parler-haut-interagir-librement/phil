# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Product`, `ProductComponent`, `ProductTranslation` | Every rule of creation, renaming, repricing, classification and attributes; withdrawal; the offer in a channel, withdrawn or outside the selection, and a variant's from its family; axes, variants and their values, composites and their parts, every refusal; names translated; serial numbers on physical products and their variants; pay what you want and its bounds, community copy prices |
| `PriceBook`, `PriceTier`, `PriceBookOrder` | Conditions of group, channel and period, the end excluded; quantity tiers; replacing and removing a price; `after` lists, their bounds and every refusal; books ordered by code then by their lists, unknown names passed over, loops set aside |
| `ProductVariants`, `CompositeProducts` | Variants created, found by their values in any order, withdrawn ones on demand, the choices of those still sold, a SKU or values taken refused; attributes over the family's, what a family passes down, the range of prices of the variants still sold; parts checked against the catalogue, one level deep, read from an order line |
| `PriceRuleSellables`, `CommunityCopies` | A chosen price declared as an amount option from the base price up to the product's maximum or the site's default, its label translated, refused for a product without a maximum, a product whose buyer no longer chooses, or an order in another currency; sold within its bounds and refused out of them or left unset, another price making another line, through a real `OrderModifier`, `OptionSupplementProcessor` and the channels' selections; a community copy at its price, described through the translator or in English; funding once per order, from copies bought and from what is paid above the suggested price, SKUs of digits included; claims one per person while copies are left, under the product's lock; the core's `OrderPurchased` |
| `PriceBookOrderMigration` | A table ordered by `priority` built, migrated, and compared with the mapping; the order of the books kept, withdrawn ones included; an empty table; a second run changing nothing — on SQLite in the unit suite, on MySQL 8 and PostgreSQL 17 in the integration suite |
| `ProductAvailability` | A product's offer in a channel, a variant's made stricter by its family's, a family left to its direct link, a family gone, other types left to others; a product not orderable refused by a real `OrderModifier` and answered by `SellableOffers` |
| `DbalProductListing` | The selection of a channel or of every channel, the stricter offer of a variant and its family, what only its direct link reaches never listed, a stored row orderable without its price read as not orderable, families left out on demand, the cursor across pages and batches, both directions, ten batches at most, names along the locales chain, the bounds of a page — on SQLite in the unit suite, on MySQL 8 and PostgreSQL 17 in the integration suite |
| `CatalogProductSource`, `ProductFeeds`, the command and the handler | Every field read from attributes, axes and templates, the sale price from the price books when lower, the availability of a product not orderable, a product listed without its price left out, an item the feed cannot carry left out and logged, the pages read, feeds published by name, by the message and by the command, an unknown or unwritable feed reported |
| `ProductFeedController`, `config/routes.php` | A feed's file with its media type, `nosniff`, `Last-Modified`, its entity tag and its `max_age`; `304` on the tag or the date sent back; a feed not declared, not yet published or a directory not found; the route's path, requirement and methods |
| `ProductTexts`, `ProductSellables` | Names read along a fallback chain from stored translations, a translation written in its own locale, lines described in the buyer's locale, families never sold |
| `TaxonomyProductClassification` | Tags and categories set, read and forgotten on the tables of `phil/taxonomy-bundle`, products of a category's branch, an unknown category refused |
| `ProductSellables`, `PriceBookPrices` | Base prices, withdrawn products, the first book that applies and prices winning, other currencies and what is not a product skipped, the books read once until reset, a line priced through a real `OrderModifier` |
| Mapping | Every column of the five tables — products, their translations, price books, the funding and the claims of community copies —, as an application's migration creates them |
| `PhilCommerceCatalogBundle` | The resolvers plugged in, the listing and the feeds wired with their command and handler, the links and fields of a feed checked, the price books reset with the kernel, community copies listening to `OrderPurchased`, translations configured or not, the taxonomy kept or removed, the Doctrine adapters and mapping, the refusal without the core |

A functional suite under `tests/Functional/` boots a real kernel with FrameworkBundle, DoctrineBundle on an in-memory
SQLite database, the core and the module: `CatalogFlowTest` stores products and price books, reads them back, prices
and taxes orders through the container, stores a family, its variants and a composite product and sells them as lines,
refuses to sell the family, reads a translated name back, and funds community copies from an order the core's
`OrderPurchased` names, twice delivered and counted once, then claims one inside a transaction, and publishes the feed
of its configuration from the stored catalogue. Installed on its own, where FrameworkBundle is a development
requirement, the suite is skipped.

The integration suite, `tests/Integration`, runs `PriceBookOrderMigration` and `DbalProductListing` on MySQL 8 and
PostgreSQL 17 when
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` name a server, and is skipped otherwise:

```sh
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/commerce-catalog-bundle/tests/Integration
```

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-catalog-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Fuzzing

`tests/Fuzz/chosen-price.php` feeds the value of a chosen price's `price` option with text nobody wrote: whatever it
reads, the option refuses it, or keeps it as written and charges that many minor units, within the product's bounds.
From the root of the monorepo:

```sh
composer fuzz -- chosen-price
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `PriceBookPrices::priceFor()`, with the books already read and
on the first line of a request, on books of 10 to 500 SKUs. From the monorepo root:

```bash
composer bench -- packages/commerce-catalog-bundle/tests/Benchmark
```
