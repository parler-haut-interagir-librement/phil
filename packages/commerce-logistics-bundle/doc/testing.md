# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Location`, `StockLevel`, `StockMovement`, `Reservation` | Every movement and its bounds, counts below the reservations, nothing below zero, reservations held once |
| `UnitsShipped`, `ShippedLine` | One event per call that moved units, a line per SKU and location, backordered units on the line of their level, the moment in UTC, nothing for an order with nothing held, the journal written first, units shipped without a dispatcher |
| `Stock::returnFromCustomer()`, `Reservation::returnPart()` | Units back on any shelf under the order's number, as `returned`; never more than left and did not come back, over the order's reservations of the SKU in their order; another SKU not counted; nothing moved on a refusal |
| `Stock::adjust()`, `StockLevel::adjust()` | Differences up and down in the journal as `adjusted`, down to zero and not below, a difference of zero refused before any lookup, less on hand than reserved |
| `PurchaseLine` | The unit cost and owner carried to the receipt, numeric SKUs, units-only lines storing nothing, every bound of a line |
| `Costing`, `CostLayer`, `CostShare`, `ShopUnitsFirst` | Both methods through receipts, shipments, counts, adjustments, transfers and returns; landed costs shared to the minor unit, their remainders, by units when every line costs nothing; uncosted units; returns at the cost they left at; consigned units outside every layer of value; an application's `StockOwnership` asked and its wrong answers refused |
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
| `PaidShipmentAuthorization`, `AnyShipmentAuthorization`, `DenyShipmentForcing`, `ShipmentForced` | An order unpaid, with a deposit only, a payment not captured, paid in one or two parts, paid more, refunded below or down to its total, of nothing; the first authorization that says yes ending the search; nobody forcing by default; a forced shipment kept with who and why, its units off the shelf, announced in UTC; its actor and reason checked, a placed order and what is left still required |
| `Parcel`, `ShippingAddress`, `LabelRequest`, `ShippingCost`, `ShippingMargin` | Every bound, incomplete addresses refused for a label, one-line fields, a margin that is a loss |
| `ShippingLabels` | Every carrier asked, the silent ones left out, the cheapest first, one carrier's failure shown, a cost recorded once, the margin in the order's currency |
| `CanadaPostCarrier`, `CanadaPostCredentials` | The requests sent — token, headers, JSON bodies, timeouts — and the answers read, through Symfony's `MockHttpClient` on answers kept in `tests/Fixtures/CanadaPost`; the token kept, renewed and reset; every answer it cannot trust refused, links to other hosts refused, answers too large cut off |
| Mapping | Every column of the eighteen tables, `returned` 0 by default, every value of the cost enumerations fitting its column |
| `PhilCommerceLogisticsBundle` | The services with DoctrineBundle, nothing without but the document types, the mapping, the carriers' and printers' tags, the core's tags, the series and the convention window configured, Canada Post configured or not, costing off by default and configured, its currency required, the refusal without the core |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and
the module: `LogisticsFlowTest` receives stock, reserves and ships an order across two locations, charges its
delivery, and reads every level, reservation and journal line back; it stores the cost of two shipments and reads
the order's margin back; it stores backorders, conventions and print jobs, purchase orders, serial numbers, a picking
slip numbered by the core's register and waves, and reads each back through its own query; and it commits the stock of
a preorder through the core's `DeferredSales`. `StockPortsFlowTest` announces a partial shipment to a listener of the
kernel's event dispatcher once, however many flushes follow, takes a customer return back, adjusts a level, and reads
back the unit cost and owner of a purchase order line, and a purchase order without terms stored as null.
`CostingFlowTest` configures costing first in, first out, receives with a landed cost and consigned units, ships,
writes off a theft, takes a return back and reads the value through `InventoryValuation`. No test
calls a carrier over the network. Installed on its own, where FrameworkBundle is a development requirement, the suite
is skipped.

The Doctrine adapter runs on SQLite in the unit suite, `tests/Unit/Infrastructure/SqliteLogisticsTest`, and on MySQL
8.4 and PostgreSQL 17 in the integration suite, `tests/Integration` — its tables created from the mapping, each query's
filters and order, ties broken by its next key, purchase orders with numeric SKUs and the terms of their lines, serial numbers,
conventions found by their days, documents with and without a shipment, shipments and waves read back; two
adjustments of one level made at once in two entity managers, the second refused by the level's version and retried,
both counted; cost layers and entries read within one unit of work before they are flushed and across units of work,
the stock valued at a moment, the cost of sales of a period and the cost journal paged; a database of 0.1.0-alpha
compared with the mapping, which adds `returned`, `line_terms`, `reason` and the two tables of costing and changes
nothing else, its rows read back after the change. SQLite runs on a file of the temporary directory, or the one
`PHIL_TEST_SQLITE_PATH` names, and needs no server; MySQL and PostgreSQL run when `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` name a
server, and are skipped otherwise:

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

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. `CostingBench` measures a shipment
emptying 1 to 100 cost layers under both methods, and a landed cost shared over 1 to 100 lines. From the root of the monorepo:

```sh
composer bench -- packages/commerce-logistics-bundle/tests/Benchmark
```
