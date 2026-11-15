# Changelog

All notable changes to `phil/product-feeds` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-15

### Added

- `FeedItem`: one product or variant in the fields of the product data specification — identifier, title,
  description, link, image and up to ten additional images, availability and its date, price, sale price and
  `SalePeriod`, `Condition`, brand, GTIN, MPN, item group, colour, size, material, pattern, product type, product
  category, age group, gender, shipping costs, shipping weight in grams, multipack, bundle, five custom labels —, each
  checked when the item is made: text in UTF-8, cleaned of what XML cannot hold and bounded in characters, identifiers
  in visible ASCII, a GTIN of 8, 12, 13 or 14 digits, not all zeros, whose check digit holds, prices from `phil/money`
  with a sale price below the price in its currency, addresses from `phil/url`, a hundred shipping costs at most;
  `hasIdentifier()`, and `identifier_exists` written `no` without a GTIN or an MPN.
- `Availability` (`InStock`, `OutOfStock`, `Preorder`, `Backorder`, `takesADate()`), `Condition` (`New`,
  `Refurbished`, `Used`), `AgeGroup` (`Newborn`, `Infant`, `Toddler`, `Kids`, `Adult`) and `Gender` (`Male`,
  `Female`, `Unisex`).
- `Shipping`: what delivering an item to a country costs, by one service or by any — an ISO 3166-1 alpha-2 country, a
  price not negative, a service name without a colon or a comma.
- `FeedChannel`: the feed's title, link and description.
- `FeedFormat`: `Xml`, RSS 2.0 with the `g:` namespace, each shipping cost an element of sub-fields, through
  `Serializer\RssSerializer`, and `Tsv`, a header row then one row per item, shipping costs written
  `country:region:service:price`, through `Serializer\TsvSerializer`; each format's extension and media type.
- `FeedPublisher`: the items of a `Contract\ProductSource` written one at a time to a temporary file beside the feed,
  then renamed over it, in constant memory; the previous file kept and the temporary one removed on any failure; a
  maximum of items, a million by default; `PublishedFeed` with the path, items and bytes.
- `Contract\FeedSerializer`, for another format.
- `Exception\ProductFeedException`, implemented by `InvalidFeedItem` and `FeedNotWritten`.
- The fuzz target `feed-item` and the benchmark `FeedPublisherBench`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
