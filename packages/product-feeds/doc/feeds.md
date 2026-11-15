# Feeds

A feed is a file a comparison site or a marketplace fetches on a schedule: one item per product or variant the store
sells. The package writes it from a `ProductSource`, in one of two formats, and puts it in place whole.

## Items

A `FeedItem` is made with its five required fields, then any of the others by name:

```php
new FeedItem(
    'SHIRT-M-RED',
    'Shirt',
    Url::fromString('https://shop.example/shirt?size=m&colour=red'),
    Money::of(3_000, Currency::of('CAD')),
    Availability::InStock,
    description: "Soft cotton.\nWashes cold.",
    imageLink: Url::fromString('https://shop.example/images/shirt-red.jpg'),
    salePrice: Money::of(2_400, Currency::of('CAD')),
    salePeriod: new SalePeriod(new DateTimeImmutable('2026-11-27'), new DateTimeImmutable('2026-12-01')),
    brand: 'Gnome',
    mpn: 'SH-1',
    itemGroupId: 'SHIRT',
    color: 'Red',
    size: 'M',
    ageGroup: AgeGroup::Adult,
    gender: Gender::Unisex,
    shipping: [
        new Shipping('CA', Money::of(500, Currency::of('CAD'))),
        new Shipping('CA', Money::of(1_200, Currency::of('CAD')), 'Express'),
    ],
    shippingWeight: 250,
    customLabels: ['Autumn sale'],
);
```

| Field | Type | Rule | Written as |
|---|---|---|---|
| `id` | string | 1 to 50 visible ASCII characters, unique in the feed | `id` |
| `title` | string | 1 to 150 characters | `title` |
| `link` | `Url` | The product's page | `link` |
| `price` | `Money` | Not negative | `price`, `25.00 CAD` |
| `availability` | `Availability` | `InStock`, `OutOfStock`, `Preorder`, `Backorder` | `availability`, `in_stock` |
| `description` | string | 5 000 characters at most; line breaks kept | `description` |
| `imageLink` | `Url` | The main image; Google Merchant Center refuses an item without one | `image_link` |
| `additionalImageLinks` | list of `Url` | 10 at most | `additional_image_link`, once per image |
| `availabilityDate` | `DateTimeImmutable` | Only with `Preorder` or `Backorder`; written in UTC | `availability_date` |
| `salePrice` | `Money` | In the price's currency, below it, not negative | `sale_price` |
| `salePeriod` | `SalePeriod` | Only with a sale price; its end after its start | `sale_price_effective_date`, an ISO 8601 interval in UTC |
| `condition` | `Condition` | `New` by default, `Refurbished`, `Used` | `condition` |
| `brand` | string | 70 characters at most | `brand` |
| `gtin` | string | 8, 12, 13 or 14 digits, not all zeros, whose last one checks the others | `gtin` |
| `mpn` | string | 70 characters at most | `mpn` |
| `itemGroupId` | string | 1 to 50 visible ASCII characters, shared by the variants of one product | `item_group_id` |
| `color`, `size`, `pattern` | string | 100 characters at most | `color`, `size`, `pattern` |
| `material` | string | 200 characters at most | `material` |
| `productType` | string | 750 characters at most, the store's own category path | `product_type` |
| `googleProductCategory` | string | 750 characters at most: a category of the product taxonomy the specification publishes, by its number or its path | `google_product_category` |
| `ageGroup` | `AgeGroup` | `Newborn`, `Infant`, `Toddler`, `Kids`, `Adult`; asked of clothing | `age_group`, `adult` |
| `gender` | `Gender` | `Male`, `Female`, `Unisex`; asked of clothing | `gender`, `unisex` |
| `shipping` | list of `Shipping` | 100 at most, one per country and service | `shipping`, once per cost |
| `shippingWeight` | int | 1 to 1 000 000 grams | `shipping_weight`, `250 g` |
| `multipack` | int | 2 to 10 000 units of one product sold as one item | `multipack` |
| `isBundle` | bool | `false` by default: a main product sold with others the store put together | `is_bundle`, `yes` when true |
| `customLabels` | list of string | 5 at most, 100 characters each; a blank one keeps the next ones at their number | `custom_label_0` to `custom_label_4` |

An item with neither a GTIN nor an MPN is written with `identifier_exists` set to `no`, as a handmade or custom
product is.

**Text** is checked the same way in every field. It must be UTF-8, or the item is refused. The control characters XML
1.0 cannot hold, and the code points U+FFFE and U+FFFF, are removed; runs of spaces, tabs and line breaks become one
space, except in a description, which keeps its line breaks; the text is trimmed. Blank text is no value, and a blank
title refuses the item. Lengths count characters, not bytes.

**Shipping** is what delivering the item costs, by country and, when the store has several, by service. A
`Shipping` holds the country, two capital letters of ISO 3166-1, the price, a `phil/money` value not negative and
possibly zero, and an optional service name of 100 characters at most that holds neither a colon nor a comma, which
separate the values of the TSV file. An item without one leaves its shipping to the rates a reader holds in the
store's account.

**Addresses** are `phil/url` values: absolute, HTTP or HTTPS, without credentials, in their canonical form. A source
makes them with `Url::fromString()`, which refuses anything else.

A field the item cannot carry throws `InvalidFeedItem`, with a message naming the item and the field.

## The channel

A `FeedChannel` is what the feed says of itself: the store's name, 1 to 150 characters, its address, and a
description of 1 to 5 000 characters. The RSS file writes them in its `channel`; the TSV file has no place for them.

## The two formats

`FeedFormat` names them and gives each one's serializer, extension and media type.

| Format | File | Media type |
|---|---|---|
| `FeedFormat::Xml` | RSS 2.0, `<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">`, one `<item>` per item, each field a `g:` element | `application/rss+xml; charset=UTF-8` |
| `FeedFormat::Tsv` | A header row naming the fields, then one row per item, every field in its column, empty ones included | `text/tab-separated-values; charset=UTF-8` |

In RSS, a field without a value is left out, each shipping cost is a `g:shipping` element holding its `g:country`,
`g:service` and `g:price`, and the text is escaped for XML. In TSV, a tab or a line break inside a value becomes a
space, so every row keeps its columns; the additional images are one value separated by commas, a comma inside an
address written `%2C`; the shipping costs are one value separated by commas, each `country:region:service:price`
with the region left empty, `CA:::5.00 CAD,CA::Express:12.00 CAD`. Both files end each line with a line feed.

Readers that take this specification read the same files: Google Merchant Center and Meta's catalogues both fetch
the RSS or the TSV file at its address. What each reader requires beyond the five fields differs: Google refuses an
item without an image, Meta one without a description and a brand, and both ask the age group, the gender, the colour
and the size of clothing sold in some countries. The source gives what its readers ask.

## Safety of the file

- **Escaping.** Every value is cleaned when its item is made, then escaped by the serializer: in RSS, `&`, `<`, `>`,
  `"` and `'` become entities, and element names are the specification's, never data; in TSV, the separators of a
  row — tab, carriage return, line feed — never reach a cell, and a cell needs no quoting.
- **Spreadsheets.** The TSV file is written for the readers of feeds, which take each cell as text. A cell is not
  altered to keep a spreadsheet from reading `=`, `+`, `-` or `@` at its start as a formula: doing so would change
  the titles a reader shows. Open the file in a spreadsheet only with its formulas disabled, or import it as text.
- **Memory.** A feed is never held whole: the publisher writes each item as the source gives it.
- **Length.** The publisher stops at its maximum of items, and each field at its maximum of characters, so a feed's
  size has a bound.
- **Workers.** The publisher, the serializers and the items keep no state between two feeds.

## Publishing the file

```php
$feed = new FeedPublisher()->publish($source, $channel, FeedFormat::Tsv->serializer(), $path);
```

1. The directory of `$path` must exist and be writable; otherwise `FeedNotWritten` is thrown and nothing is written.
2. A temporary file is created in that directory, named after the feed and starting with a dot.
3. The header, then each item as the source gives it, then the footer are written to it. Only the counts are kept, so
   memory stays flat whatever the length of the catalogue: the benchmark writes ten thousand items in about 5 MB,
   the same as a thousand.
4. The file is flushed, closed, made readable by all (`0644`), and renamed over `$path`. On one file system the rename
   is atomic: a reader fetches the previous file or the new one, never half of either.

If the source throws, the disk fills, or the rename fails, the temporary file is removed, the previous feed stays in
place, and the exception goes up. A source that gives more items than the publisher's maximum — a million by
default, `new FeedPublisher(maxItems: 50_000)` for another — is refused with `FeedNotWritten`, so that a source that
never ends does not fill the disk.

`publish()` returns a `PublishedFeed`: the path, the number of items and the size in bytes.

The publisher keeps nothing between two feeds: one instance serves every request or message of a long-running
worker.

## Scheduling the file

A reader fetches the file at the address it was given, on its own schedule; the store writes it again as often as
its catalogue changes, every few hours in most stores. The library writes one file per call and schedules nothing.
In a Symfony application, the bundle that implements `ProductSource` dispatches a message that publishes the feed, and
the application's schedule of Symfony Scheduler sends that message: `phil/commerce-catalog-bundle` does so for its
products.

## What is not written

- **A marketplace's API.** Google's Merchant API, and the catalogue APIs of other marketplaces, take products one by
  one and see a change within minutes. The file is what every one of them reads; an adapter of an API waits for a site
  whose prices or stock change faster than its schedule.
- **A reader's own feed rules.** A reader's rules that rename or fill fields are kept on the reader's side.
- **Fields of fewer readers.** Regional shipping (a region or postal codes inside a country), unit pricing, tax,
  installments, energy labels, product highlights and details, destinations a reader leaves out, and the attributes
  of one reader alone are not written. A source that needs one writes its own `FeedSerializer`.
- **Restricted GTINs.** A GTIN is checked by its length and its check digit; the ranges GS1 keeps for a store's own
  use or for coupons are not refused, and a reader that refuses them says so in its report.
- **Countries checked against a list.** A country is two capital letters; whether ISO 3166-1 assigns them is left to
  the reader, so that the library needs no list of countries.
- **Deduplication.** Each identifier is given once by the source: checking it in the publisher would keep every
  identifier in memory.
