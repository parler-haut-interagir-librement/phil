# Product feeds

With `phil/product-feeds` installed, the module publishes its catalogue as feed files that comparison sites and
marketplaces fetch on a schedule: RSS 2.0 with the `g:` namespace of product data, or TSV. Each feed is declared in
the configuration and written whole by the library, in constant memory, then renamed over the previous file.

```sh
composer require phil/product-feeds
```

```yaml
# config/packages/phil_commerce_catalog.yaml
phil_commerce_catalog:
    product_feeds:
        google:
            path: '%kernel.project_dir%/public/feeds/google.xml'
            format: xml
            title: 'The Dice Shop'
            link: 'https://shop.example/'
            description: 'Dice, games and accessories.'
            channel: web
            locale: fr_CA
            product_link: 'https://shop.example/products/{sku}'
            image_link: 'https://shop.example/images/{sku}.jpg'
            attributes:
                color: colour
                gtin: ean
```

The options are described in [Configuration](configuration.md#product_feeds).

## What a feed publishes

`CatalogProductSource` is the library's `ProductSource` for one feed. It reads the products the feed's channel lists
through `ProductListing`, two hundred per page, families left out: a product left to its direct link, or hidden, is
never published, and a product listed without its price is left out, since every item of a feed has one.

| Field of the feed | Read from |
|---|---|
| `id` | The SKU |
| `title` | The name in the feed's locale, along the chain of `translations.locales`; the product's own name without one |
| `link` | `product_link`, `{sku}` and `{family}` replaced, each percent-encoded; `{family}` is the product's own SKU when it has none |
| `image_link` | The `image` attribute when it holds an address; `image_link` otherwise, built the same way |
| `price` | The base price |
| `sale_price` | The price books' price for one unit, no customer group, the feed's channel and the moment the feed is written, when lower than the base price |
| `availability` | `out_of_stock` for a product no order may hold; otherwise the `availability` attribute when it names `in_stock` or `out_of_stock`, else `in_stock` |
| `item_group_id` | The family's SKU, for a variant |
| `description`, `brand`, `gtin`, `mpn`, `condition`, `color`, `size`, `material`, `pattern`, `product_type` | The variant's axis, then the attribute, of the name `attributes` gives the field — its own name by default |

A `preorder` or `backorder` attribute is written `out_of_stock`: the catalogue holds no availability date, which a
reader requires with them. Stock levels are the logistics module's, which the feed does not read; a product sold out
for good is made not orderable, and the feed writes it out of stock.

A product the feed cannot carry — a GTIN whose check digit fails, an image attribute that is no web address, a name
the library refuses — is left out and logged as a warning with its SKU and the reason, so that one product never
stops the feed. A feed written is logged as information, with its items and bytes.

## Publishing

`ProductFeeds` publishes a feed by its name: `publish('google')` returns the library's `PublishedFeed`. Three ways
call it:

| Way | When |
|---|---|
| `bin/console phil:commerce-catalog:publish-feeds [feed…]` | At a deployment or from a cron job: every feed, or those named; the status is a failure when one feed fails, the others still published |
| The message `PublishProductFeed` | With Symfony Messenger installed, `PublishProductFeedHandler` publishes the feed it names; the message carries the name only |
| `ProductFeeds::publish()` | From the application's own code, after an import, say |

The schedule is the application's, with Symfony Scheduler:

```php
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeed;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('feeds')]
final class FeedSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every('6 hours', new PublishProductFeed('google')));
    }
}
```

A failure — a directory missing or not writable, a full disk — throws `FeedNotWritten`, and the previous file stays in
place.

## Serving

A file written under the public directory is served by the web server from where `path` puts it; the reader is given
its address. A file written elsewhere — `%kernel.project_dir%/var/feeds/google.xml`, say — is served by the route the
bundle declares, once the application imports it:

```yaml
# config/routes/phil_commerce_catalog.yaml
phil_commerce_catalog:
    resource: '@PhilCommerceCatalogBundle/config/routes.php'
```

`GET /product-feeds/google` answers the file of the feed `google` with its media type — `application/rss+xml` or
`text/tab-separated-values` —, `X-Content-Type-Options: nosniff`, its `Last-Modified` date and an entity tag made of
its time and size, never of its content. Caches keep it public for the feed's `max_age`, an hour by default; a reader
that sends the tag or the date back is answered `304 Not Modified`. A feed not declared, or not yet published, is not
found. Only the feed's name comes from the address: the file is the one `path` names.

## What is not written

Pushing products to Google's Merchant API, or to another marketplace's, item by item, is not written: every reader
takes the file, and an adapter of an API is a later step, for a site whose prices or stock change faster than its
schedule.
