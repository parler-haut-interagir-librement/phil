# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Location`, `StockLevel`, `StockMovement`, `Reservation` | Every movement and its bounds, counts below the reservations, nothing below zero, reservations held once |
| `DeliveryMethod` | Every rule, zones and currency served, the free threshold |
| `Stock` | The journal of each movement, unknown and same locations, closed locations, reservations by priority, shortages reserving nothing, release and shipment once, backorders reserved at the first open location and shipped only once received, nothing moved when a level lacks units on hand |
| `StockPolicy`, `DeferredStock` | Backorders allowed and forbidden, none without a policy store; the stock of a deferred sale held and freed at the clock's moment |
| `ConventionStock`, `Convention`, `ConventionQuantity`, `PrintJob` | Quantities entered and what is left to print, the window at both ends, orders placed online sent to print or reserved by what is available, another channel taking from stock, a route decided once, the `PrintOnDemand` services called once per order and SKU |
| `Purchasing`, `PurchaseOrder` | Receipts into the journal under the order's number, numeric SKUs, quantities beyond what is expected, closed orders, every bound of an order |
| `SerialNumbers`, `SerialNumber` | Registered, moved, shipped and put back; never more than on hand, a serial number once, batches bounded |
| `ShippingDocuments`, `LogisticsDocumentTypes` | The two series and their prefixes, picking slips by location with the untracked lines last, delivery notes with the gift message and hidden prices, a document numbered once |
| `ShippingWaves`, `ShippingWave` | Orders planned, moved and withdrawn, waves rescheduled and listed by day, a released wave left as it is |
| `DeliveryOptions`, `ShippingProcessor` | Methods offered by priority, the charge through a real `OrderModifier`, free after promotions, methods no longer serving |
| `Shipments`, `Shipment`, partial reservations | Several parcels, what is left, the fulfilment state, untracked lines, refusals, releasing after a part |
| `Parcel`, `ShippingAddress`, `LabelRequest`, `ShippingCost`, `ShippingMargin` | Every bound, incomplete addresses refused for a label, one-line fields, a margin that is a loss |
| `ShippingLabels` | Every carrier asked, the silent ones left out, the cheapest first, one carrier's failure shown, a cost recorded once, the margin in the order's currency |
| `CanadaPostCarrier`, `CanadaPostCredentials` | The requests sent — token, headers, JSON bodies, timeouts — and the answers read, through Symfony's `MockHttpClient` on answers kept in `tests/Fixtures/CanadaPost`; the token kept, renewed and reset; every answer it cannot trust refused, links to other hosts refused, answers too large cut off |
| Mapping | Every column of the sixteen tables |
| `PhilCommerceLogisticsBundle` | The services with DoctrineBundle, nothing without but the document types, the mapping, the carriers' and printers' tags, the core's tags, the series and the convention window configured, Canada Post configured or not, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `LogisticsFlowTest` receives stock, reserves and ships an order across two locations, charges its
delivery, and reads every level, reservation and journal line back; it stores the cost of two shipments and reads
the order's margin back; it stores backorders, conventions and print jobs, purchase orders, serial numbers, a picking
slip numbered by the core's register and waves, and reads each back through its own query; and it commits the stock of
a preorder through the core's `DeferredSales`. No test calls a carrier over the network. Installed on its own, where FrameworkBundle is a
development requirement, the suite is skipped.

An integration suite, `tests/Integration`, runs the Doctrine adapter on MySQL 8 and PostgreSQL 17 — its tables created from the mapping, purchase orders with numeric SKUs, serial numbers, conventions found by their days, documents with and without a shipment, shipments and waves read back —
when `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` name a server, and is skipped otherwise:

```sh
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/commerce-logistics-bundle/tests/Integration
```

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-logistics-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/commerce-logistics-bundle/tests/Benchmark
```
