# Changelog

All notable changes to `phil/commerce-logistics-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.1-alpha - 2026-11-26

### Changed

- Accepts `phil/ddd-bundle` 0.2 as well as 0.1 (`^0.1@dev || ^0.2@dev`), so that it installs beside the typed identifiers in
  controllers and the Serializer.

## 0.3.0-alpha - 2026-11-25

A behaviour change in alpha, a minor bump — an order ships once its payments cover what
it is due, or once an authorization of the application says so, and the table of shipments gains two columns,
`forced_by` and `force_reason`, null for the shipments already there.

### Added

- Authorized shipments: the port `ShipmentAuthorization`, tagged `phil_commerce_logistics.shipment_authorization` by
  autoconfiguration, which `Shipments::ship()` asks through `AnyShipmentAuthorization`, an order leaving when one says
  yes; `PaidShipmentAuthorization`, the bundle's own, says yes once the payments of the core's `Payments::summary()`
  cover what the order is due; `Shipments::isAuthorized()`; `StockRefused::notAuthorized()`.
- Forced shipments: `Shipments::force()`, for whoever the port `ShipmentForcing` allows — `DenyShipmentForcing`, the
  default alias, refuses everyone —, with a reason of 1 to 255 characters on one line; `Shipment::forced()`,
  `wasForced()`, `forcedBy` and `forceReason`, the columns `forced_by` and `force_reason` of the table of shipments,
  null for a shipment an authorization let leave; the event `ShipmentForced`, the shipment, the order and who forced it,
  dispatched through the service `event_dispatcher` when there is one; `StockRefused::forcingRefused()`.

### Changed

- `Shipments` takes a `ShipmentAuthorization` as its third argument, then a `ShipmentForcing` and an event dispatcher,
  both optional; the bundle wires them. An order whose payments do not cover what it is due — unpaid, a deposit only, a
  payment pending — no longer ships; an application that shipped such orders adds its own authorization, or forces them.

### Security

- An order ships only once it is paid or otherwise authorized: before, `Shipments::ship()` let any placed order leave, a
  deposit or nothing paid at all; forcing it takes a permission, and the shipment keeps who forced it and why.

## 0.2.0-alpha - 2026-11-24

### Added

- `UnitsShipped` and `ShippedLine`: `Stock::ship()` and `Stock::shipPart()` dispatch, by its class, once per call that
  moved units, the order's number and, per SKU and location, the units that left, at the call's moment in UTC; no
  personal data. It is dispatched after the movements are written to the unit of work and before the application
  flushes it, through the service `event_dispatcher` when there is one; the bundle requires
  `symfony/event-dispatcher-contracts`.
- Customer returns: `Stock::returnFromCustomer()` puts units a customer sent back on a shelf under the order's number,
  never more of a SKU than left with the order and did not come back yet, refused otherwise with
  `StockRefused::overReturned()`; the movement kind `returned`; `Reservation::shipped()`, `returned()`, `returnable()`
  and `returnPart()`, and the reservation's `returned` column, 0 by default.
- Adjustments by a difference: `Stock::adjust()` and `StockLevel::adjust()` change what is on hand by a difference,
  never below zero, refused otherwise with `StockRefused::belowZero()`, under the level's version; the movement kind
  `adjusted`.
- The unit cost and owner of purchase order lines: `PurchaseLine`, which `Purchasing::order()` and `PurchaseOrder::of()`
  take in place of the units of a SKU, with an optional `Money` unit cost and an optional owner, the caller's own
  identifier; `PurchaseOrder::line()`, which a receipt reads back; the purchase order's `line_terms` column, null when
  no line gives them. A database of 0.1.0-alpha gains the two columns and nothing else changes.
- Costing, off by default: the configuration `costing.enabled`, `costing.method` — `weighted_average` or `fifo` — and
  `costing.currency`; `Costing`, which `Stock` and `Purchasing` call with each movement, `CostLayer` and `CostEntry`,
  tables `phil_commerce_logistics_cost_layer` and `_cost_entry`, `CostingMethod`, `CostEntryKind`, `CostShare` and
  `CostRefused`; `CostRepository`, implemented by `DoctrineLogisticsRepository`, which finds the layers and entries
  given to it and not flushed yet. Units come in at the unit cost of their purchase order line, or the one given to
  `Stock::receive()`, and leave at the pool's average or from the oldest layer, in integers through
  `Money::allocate()`; units without a known cost are counted apart, as uncosted.
- Landed costs: `Purchasing::receive()` takes the freight, duties and brokerage of a receipt and shares them over its
  lines of the shop's units with a unit cost, in proportion to their value, to the minor unit.
- Variances by reason: `VarianceReason`, `Stock::adjustFor()` and the reason of `Stock::count()`, kept in the stock
  journal's `reason` column, null by default, and, with costing, in the cost journal beside the value of the units at
  cost.
- Customer returns at their cost: with costing, `Stock::returnFromCustomer()` puts the units back at their share of
  what the order's issues cost, under either method.
- Consigned units: units received with an owner hold no value and never enter the shop's layers; `StockOwnership`,
  whose alias an application points at its own service, says whose units leave a level the shop and owners share, and
  `ShopUnitsFirst` lets the shop's own leave first.
- `InventoryValuation`, a public alias of `InventoryValue`: the unit cost of a SKU, the stock valued at a moment
  (`ValuedStock`), the cost of sales of a period (`SaleCost`) and the cost journal, a page at a time.

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `symfony/event-dispatcher-contracts` 3.8,
  `symfony/http-client-contracts` 3.8, `symfony/polyfill-php86` 1.43, …), and of each development requirement
  (`doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Changed

- It requires `phil/commerce-bundle` 0.5, the minor released the same evening.

## 0.1.0-alpha - 2026-11-20

### Added

- `Location`, `StockLevel`, `StockMovement`, `Reservation`, `MovementKind`, `ReservationState`, `StockRefused`; the
  `Stock` service, whose `reserve()` reserves an order once, leaving an order that already has reservations as it
  is, refuses an order that is not placed (`StockRefused::notReservable()`), and looks the stock up once per order,
  for its product lines only, the configured lines of one SKU reserved together from the same stock; a shipment that
  asks a level for more units than it holds on hand is refused before anything moves (`StockRefused::notOnHand()`).
- Backorders: `StockPolicy` and `StockPolicyRepository`, table `phil_commerce_logistics_stock_policy`, a flag per SKU
  with neither threshold nor ceiling; `Stock::allowBackorders()`, `forbidBackorders()`, `allowsBackorders()` and
  `backordered()`, `StockLevel::backorder()` and `backordered()`, `Reservation::backorder()`, the movement kind
  `backordered`: the shortfall of a SKU that allows backorders is reserved at its first open location.
- `DeferredStock`, the core's `StockCommitments`, tagged `phil_commerce.stock_commitments`: the stock of a sale paid
  later is reserved when its policy commits it and released when the sale is cancelled, at the PSR-20 clock's moment.
- Conventions: `Convention`, `ConventionQuantity`, `PrintJob` and `ConventionRepository`, tables
  `phil_commerce_logistics_convention`, `_convention_quantity` and `_print_job`; `ConventionStock`, whose `toPrint()`
  sends the convention items of an order placed online to print while a convention falls within `window_days` (30 by
  default), or when their stock is short, and whose `shortfall()` gives what is left to print for a convention;
  `Stock::reserve()` leaves those items to print and reserves the rest; the `PrintOnDemand` port, tagged
  `phil_commerce_logistics.print_on_demand` by autoconfiguration, called once per order and SKU; the configuration
  `conventions.window_days` and `conventions.online_channels`.
- Purchasing: `PurchaseOrder`, `PurchaseState`, `PurchaseRefused` and `PurchaseOrderRepository`, table
  `phil_commerce_logistics_purchase_order`; `Purchasing`, which opens purchase orders of 1 to 500 SKUs, receives
  against them into the journal under their number, closes them and gives what is on order.
- Serial numbers: `SerialNumber`, `SerialState` and `SerialNumberRepository`, table
  `phil_commerce_logistics_serial_number`; `SerialNumbers`, which registers units at a location, never more than it
  holds on hand, moves, ships and puts them back, 1 to 1,000 per call.
- Documents: `LogisticsDocumentTypes`, the core's `DocumentTypes`, tagged `phil_commerce.document_types`, with the
  series `delivery_note` and `picking_slip` and the configuration `documents`; `ShippingDocument`,
  `ShippingDocumentKind` and `ShippingDocumentRepository`, table `phil_commerce_logistics_document`;
  `ShippingDocuments`, which numbers a delivery note once per shipment and a picking slip once per order, and gives
  `DeliveryNote`, `DeliveryNoteLine`, `PickingSlip` and `PickingLine`, with the order's gift wrapping, message and
  hidden prices.
- Shipping waves: `ShippingWave`, `WaveAssignment` and `ShippingWaveRepository`, tables `phil_commerce_logistics_wave`
  and `_wave_order`; `ShippingWaves`, which plans, assigns, reschedules and releases them.
- Shipments: `Shipment`, its units by line in the column `line_items`, `FulfilmentState`, `ShipmentRepository`, `Shipments`; `Stock::shipPart()`,
  `Reservation::shipPart()` and `remaining()`, and the reservation's `shipped` column.
- `DeliveryMethod` and `DeliveryKind`: `serves()` compares currency codes, and `costFor()` and `freeFrom()` read the
  price once; `DeliveryOptions`; `ShippingProcessor`, the core's `OrderProcessor`, after the catalogue's promotions
  and before the taxes.
- Carriers: the `CarrierGateway` port, tagged `phil_commerce_logistics.carrier` by autoconfiguration; `Parcel`,
  `ShippingAddress`, `RateRequest`, `LabelRequest`, `RateQuote`, `Label`, `ShippingMargin` and `CarrierRefused`;
  `ShippingCost`, one per shipment, and `ShippingCostRepository`; `ShippingLabels`, which asks the carriers' rates,
  the cheapest first, buys their labels, records what each shipment cost and reads an order's margin against its
  delivery charge, never changing the order.
- `CanadaPostCarrier`, `CanadaPostCredentials` and `CanadaPostPayloads`: Canada Post's REST API in JSON on Symfony's
  HTTP client, its OAuth 2.0 token kept until shortly before it expires and reset between the requests of a worker,
  links followed to the API's own scheme, host and port only, each read by `phil/url` and sent as it was read,
  answers read chunk by chunk within a limit and checked field by field;
  the bundle requires `ext-mbstring`, `psr/clock`, `symfony/clock`, `symfony/http-client-contracts` and
  `symfony/service-contracts`; the field names are not yet compared with Canada Post's OpenAPI document.
- `LocationRepository`, `StockRepository` with `levelsOfSkus()`, the levels of several SKUs in one lookup,
  `DeliveryMethodRepository` and `DoctrineLogisticsRepository`, which also stores the costs of shipments and implements
  every repository of the module, its queries sorted by PHP's `\SortDirection` through `symfony/polyfill-php86`.
- `PhilCommerceLogisticsBundle`, extension alias `phil_commerce_logistics`, which refuses to load without
  `PhilCommerceBundle`; its configuration `carriers.canada_post` registers Canada Post's adapter, its credentials read
  from the environment.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
