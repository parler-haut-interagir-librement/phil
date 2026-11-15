# Installation

```sh
composer require phil/product-feeds
```

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Typed class constants, the URI extension |
| `ext-mbstring` | any | Lengths counted in characters |
| `phil/money` | ^0.1@dev | Prices and sale prices, written to the precision of their currency |
| `phil/url` | ^0.1.4@dev | Links and images, read as a browser reads them, whatever the locale of the process, and written in their canonical form; it requires `ext-intl` |

The library declares no service. In a Symfony application, the bundle that holds the products implements
`ProductSource` and publishes the feed; `phil/commerce-catalog-bundle` does so for its catalogue.
