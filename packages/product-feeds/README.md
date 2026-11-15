# phil/product-feeds

**Product feeds for comparison sites and marketplaces, written to a file in constant memory and swapped into place
whole.**

phil/product-feeds publishes a catalogue as a feed file that comparison sites and marketplaces fetch on a schedule:
RSS 2.0 with the `g:` namespace of product data, or tab-separated values with a header row. Both formats carry the
fields of the product data specification that Google Merchant Center reads, and that other readers of product feeds
read too: identifier, title, description, link, images, availability, price and sale price with its period,
condition, brand, GTIN, MPN, the group of a product's variants and the colour, size, material and pattern they differ
by, the age group and gender of clothing, the product category, shipping costs by country and service, the shipping
weight, multipacks and bundles, and five custom labels.

It is for a store that wants its products listed beyond its own pages. A `FeedItem` checks each field when it is made
— text cleaned to what XML holds, identifiers in ASCII, a GTIN whose check digit holds, prices from `phil/money`,
addresses from `phil/url` —, so a file never holds an item a reader refuses for its form. `FeedPublisher` writes the
items one at a time into a temporary file beside the feed and renames it over the feed: a reader always fetches a
complete file, and a catalogue of a hundred thousand products is written in the memory of one.

It is a plain PHP library. The catalogue comes through one port, `ProductSource`; a Symfony bundle that holds
products implements it and schedules the file, as `phil/commerce-catalog-bundle` does. Pushing products to a
marketplace's API, item by item, is not written: the file is what every reader takes, and an API adapter waits for a
site that needs changes seen within minutes.

## Status

| Part | State |
|---|---|
| `FeedItem`, `Availability`, `Condition`, `AgeGroup`, `Gender`, `SalePeriod`, `Shipping`, `FeedChannel` | Implemented and tested |
| `FeedPublisher`: the file written in constant memory, renamed into place | Implemented and tested |
| RSS 2.0 with the `g:` namespace, and TSV | Implemented and tested |
| `ProductSource` and `FeedSerializer`, the extension points | Implemented and tested |
| Pushing products to a marketplace's API | A later step, for a site that needs it |

## Documentation

| Document | What it covers |
|---|---|
| [Feeds](doc/feeds.md) | Items and their fields, the two formats, the safety of the file, publishing it, scheduling it. |
| [Installation](doc/installation.md) | Requirements and installing. |
| [Extension points](doc/extension-points.md) | The source of products, the serializer of another format. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/product-feeds
```

```php
use Phil\ProductFeeds\{Availability, FeedChannel, FeedFormat, FeedItem, FeedPublisher};
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\Money\{Currency, Money};
use Phil\Url\Url;

$source = new class implements ProductSource {
    public function items(): iterable
    {
        yield new FeedItem(
            'DICE-1',
            'Dice set',
            Url::fromString('https://shop.example/products/DICE-1'),
            Money::of(2_500, Currency::of('CAD')),
            Availability::InStock,
            imageLink: Url::fromString('https://shop.example/images/DICE-1.jpg'),
            brand: 'Gnome',
            gtin: '4006381333931',
        );
    }
};

$channel = new FeedChannel('The Dice Shop', Url::fromString('https://shop.example/'), 'Dice and games.');
$feed = new FeedPublisher()->publish($source, $channel, FeedFormat::Xml->serializer(), '/var/www/feeds/products.xml');

echo $feed->items; // 1
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/money` 0.1 and `phil/url` 0.1.4, which requires the intl and URI
extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
