# Changelog

All notable changes to `phil/commerce-logistics-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
