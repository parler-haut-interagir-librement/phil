# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core the module plugs into: its orders, `OrderProcessor`, `StockCommitments` and register of document types |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | The prices of delivery methods and the costs of shipments |
| `phil/url` | ^0.1@dev | The links of a carrier's answers, read as a browser reads them |
| `ext-mbstring` | any | The length of an address's fields, in characters |
| `doctrine/orm` | ^3.7 | The mapping attributes of the logistics entities, and the repository adapter |
| `symfony/uid` | ^8.2 | The time-ordered UUIDs of movements and reservations |
| `symfony/http-client-contracts` | ^3.6 | The HTTP client the Canada Post adapter asks through |
| `symfony/clock`, `psr/clock` | ^8.2, ^1.0 | When the carrier's access token expires |
| `symfony/service-contracts` | ^3.6 | The adapter forgets its token between the requests of a worker |
| `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle` |
| `symfony/config` | ^8.2 | The module's configuration tree |
| `symfony/polyfill-php86` | ^1.37 | PHP's `\SortDirection`, which the queries hand the ORM, on PHP 8.5 |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-logistics-bundle
```

## Registering

With Symfony Flex, the recipe of each package registers its bundle; the module's defaults need no configuration file.
Without Flex:

Register the core and the module; the order of the two lines does not matter.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceLogisticsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configuring

Every key has a default; none is required.

```yaml
# config/packages/phil_commerce_logistics.yaml
phil_commerce_logistics:
    documents:
        delivery_note_prefix: DN-   # the series of delivery notes in the core's register
        picking_slip_prefix: PS-    # the series of picking slips
        digits: 8                   # 1 to 18
    conventions:
        window_days: 30             # a convention within this many days is near: 0 to 366
        online_channels: [web]      # orders of these channels, and orders without one, are placed online
    carriers: {}                    # none by default
```

See [Carriers](carriers.md) for Canada Post, whose adapter also needs FrameworkBundle's `http_client` service, and
[Conventions](conventions.md) for the stock of convention items. The prefixes must differ from every other type of
the core's register — `R-` and `CN-` for its receipts and credit notes —, or the register refuses them both.

The services read repositories: with DoctrineBundle, the module registers them with its Doctrine adapter — generate a
migration for its sixteen tables —; without it, the application declares them with its own adapters. The core's
deferred sales and its register of document types need the core's own Doctrine adapters as well.
