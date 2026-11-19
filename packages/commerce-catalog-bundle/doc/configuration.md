# Configuration

The module's configuration lives under `phil_commerce_catalog`. Without one, every product is shown in its own name,
no feed is published, and a product without a maximum of its own is not sold at a chosen price.

```yaml
# config/packages/phil_commerce_catalog.yaml
phil_commerce_catalog:
    pay_what_you_want:
        default_maximum: 50000      # 500.00 in the product's currency
    translations:
        locales:
            fr_CA: [fr]
            fr: [en]
            en: []
```

| Option | Default | What it does |
|---|---|---|
| `pay_what_you_want.default_maximum` | `null` | The highest price a buyer may choose for a product without a maximum of its own, in minor units of the product's currency, 1 or more. Without it, such a product is not sold at a chosen price — see [Price rules](price-rules.md). |
| `translations.locales` | `{}` | The locales product names are shown in, each with the locales tried after it. A locale `phil/locale-dimensions` cannot read fails the container build. |

With locales declared, `ProductTexts` reads each name in the request's locale — the translator's, read at each call,
so a worker serves each request in its own —, and an order line takes the name in that locale as its description. A
locale not declared, or a translation whose name is not written yet, falls back to the product's own name. See
[Names and classification](names-and-classification.md).

## `product_feeds`

The feeds `phil/product-feeds` writes from the catalogue, by name; declaring one without the library installed fails
the container build. See [Product feeds](product-feeds.md).

```yaml
phil_commerce_catalog:
    product_feeds:
        google:
            path: '%kernel.project_dir%/public/feeds/google.xml'
            title: 'The Dice Shop'
            link: 'https://shop.example/'
            description: 'Dice, games and accessories.'
            product_link: 'https://shop.example/products/{sku}'
```

| Option | Default | What it does |
|---|---|---|
| `path` | required | Where the file is written, in an existing, writable directory: one the web server serves, or any other the route of `config/routes.php` serves from |
| `format` | `xml` | `xml`, RSS 2.0 with the `g:` namespace, or `tsv` |
| `title`, `link`, `description` | required | The store's name, address and description, written in the RSS channel; the link is checked by `phil/url` when the container is built |
| `channel` | `null` | The channel whose selection is published and whose price books give the sale prices; `null` publishes what every channel sells |
| `locale` | `null` | The locale of the names, along the chain of `translations.locales`; each product's own name otherwise |
| `product_link` | required | The address of a product's page; it names `{sku}`, `{family}` or both, or fails the container build |
| `image_link` | `null` | The address of a product's image, built the same way; an `image` attribute holding an address wins |
| `attributes` | `{}` | The attribute, or variant axis, a field reads when not its own name: `description`, `image`, `brand`, `gtin`, `mpn`, `condition`, `availability`, `color`, `size`, `material`, `pattern`, `product_type`; another field fails the container build |
| `allow_localhost` | `false` | Whether the addresses may be on `localhost` or under `.localhost`, in development and tests |
| `max_age` | `3600` | How long a cache keeps the file the route of `config/routes.php` serves, in seconds; zero or more |
