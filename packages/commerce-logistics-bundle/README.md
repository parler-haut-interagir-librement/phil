# phil/commerce-logistics-bundle

**Stock per location, its value at cost, order reservations, backorders, purchasing, serial numbers, parcels and their documents, delivery charged per zone, and carriers' rates and labels.**

phil/commerce-logistics-bundle keeps the physical side of an order. It holds stock per SKU and location, with a journal
of every movement: units received, counted and transferred, goods received against purchase orders, and the serial
number of each unit that has one, the unit cost and owner a purchase order line gives, customer returns put back on a
shelf, and adjustments by a difference, with the reason of a variance. With costing enabled, it values the shop's stock
at cost as it moves — weighted average or first in, first out —, shares a receipt's landed cost over its lines to the
minor unit, puts customer returns back at the cost they left at, and keeps units left on consignment out of the shop's
value. It reserves the product lines of a placed order from the open locations by
priority, sells a SKU without stock when its flag allows it, releases them, and ships them in one parcel or several,
each with its carrier, tracking number and delivery note, after a picking slip, alone or in a planned shipping wave;
the units that leave are announced by an event that a ledger or a report listens to.
It keeps the stock prepared for conventions, size by size, and sends the shirts of an order placed online to print
while a convention is near. It offers delivery methods per zone (shipping, local delivery, pickup), each with a price,
a free threshold and an estimated number of days, and charges the method the order chose. It asks carriers for their
rates and labels, and records what each shipment really cost beside the delivery price the buyer paid; Canada Post is
its first carrier adapter.

It is for a shop that sells goods it holds on a shelf. An application that sells access, downloads or services needs
only `phil/commerce-bundle`, and perhaps its subscriptions. A product never received anywhere is not tracked, so a
shop can mix both. The module does not handle supplier returns, replenishment or forecasts, it never sets the delivery
price from a carrier's rate, and it does not tax a delivery charge.

It is a Symfony bundle and a module of `phil/commerce-bundle`: it refuses to load without the core, and registers its
services with DoctrineBundle, which stores all of it. It plugs into the core through its ports: `ShippingProcessor`
is an order processor after the promotions and before the taxes, and adds the delivery charge to the order;
`DeferredStock` holds and frees the stock of the core's sales paid later; the delivery note and the picking slip are
numbered by the core's register of document types. Each stock level carries a version column, which refuses two
movements made over the same copy.

## Status

| Part | State |
|---|---|
| Locations, stock levels, journal, receiving, counts, adjustments, transfers, customer returns | Implemented and tested |
| Reservations of placed orders, released or shipped; backorders; the stock of deferred sales | Implemented and tested |
| Convention stock, print on demand for orders placed online | Implemented and tested |
| Purchase orders, the unit cost and owner of their lines, their receipts; serial numbers | Implemented and tested |
| Costing: weighted average or first in, first out, landed costs, variances by reason, returns at their cost, consigned units kept out, `InventoryValuation` | Implemented and tested |
| Shipments: an order in one parcel or several, carrier and tracking; `UnitsShipped` | Implemented and tested |
| Delivery notes, picking slips, shipping waves | Implemented and tested |
| Delivery methods per zone, free threshold, delivery charges | Implemented and tested |
| Carriers' rates and labels, the cost of each shipment, Canada Post | Implemented and tested; Canada Post's field names not yet compared with its OpenAPI document |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the module beside the core. |
| [Stock](doc/stock.md) | Locations, levels, the journal, receiving, counts, adjustments, transfers, customer returns, reservations, backorders, deferred sales, storage. |
| [Conventions](doc/conventions.md) | The stock prepared per convention, what is left to print, orders placed online sent to print. |
| [Purchasing](doc/purchasing.md) | Purchase orders and their receipts; the serial number of each unit. |
| [Costing](doc/costing.md) | The value of the stock at cost: methods, landed costs, variances, returns, owners, reading the value, storage. |
| [Delivery](doc/delivery.md) | Delivery methods, what an order pays, storage. |
| [Shipments](doc/shipments.md) | Shipping an order in one parcel or several, what is left, delivery notes, picking slips, waves, storage. |
| [Carriers](doc/carriers.md) | Rates and labels, the cost of each shipment against the delivery charged, Canada Post. |
| [Scope](doc/scope.md) | What the module holds, what lies outside it, and the ports of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-logistics-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/ddd-bundle`, `phil/money` and `phil/url` 0.1;
Doctrine ORM 3.7; `symfony/uid`, `symfony/clock`, `symfony/config` and `symfony/dependency-injection` 8.2 or later;
`symfony/event-dispatcher-contracts`, `symfony/http-client-contracts` and `symfony/service-contracts` 3.6;
`symfony/polyfill-php86` 1.37; `psr/clock` 1.0. The Canada Post adapter needs an HTTP client, `symfony/http-client`
through FrameworkBundle.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
