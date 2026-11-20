# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application extends or replaces. The ports of the
core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag and order |
|---|---|---|
| `OrderProcessor` | `ShippingProcessor`, the delivery charge | `phil_commerce.order_processor`, after the promotions and before the core's taxes |
| `StockCommitments` | `DeferredStock`, the stock of a sale paid later | `phil_commerce.stock_commitments` |
| `DocumentTypes` | `LogisticsDocumentTypes`, the delivery note and the picking slip | `phil_commerce.document_types` |

A processor of the application's own names `ShippingProcessor` in its tag's `before` or `after`. See
[Scope](scope.md).

## Carriers

`Application\Port\CarrierGateway` is the module's own port for a carrier's rates and labels. An application adds a
carrier by a service implementing it, which autoconfiguration tags `phil_commerce_logistics.carrier`; `ShippingLabels`
asks every tagged carrier. `CanadaPostCarrier` is registered when `phil_commerce_logistics.carriers.canada_post` is
enabled — see [Carriers](carriers.md).

| Configuration | What it sets |
|---|---|
| `carriers.canada_post.enabled` | Registers Canada Post's adapter; off by default |
| `carriers.canada_post.api_key`, `api_secret` | The developer application's key and secret, from the environment or the secrets vault |
| `carriers.canada_post.customer_number`, `contract_id` | The customer billed; the contract that gives commercial rates |
| `carriers.canada_post.base_uri`, `token_uri` | The API's gateway and the address of its access tokens, HTTPS only |
| `carriers.canada_post.language` | `en-CA` or `fr-CA`, the language of the carrier's messages |

## Printing on demand

`Application\Port\PrintOnDemand` receives the convention items of an order placed online that go to print. A service
implementing it is tagged `phil_commerce_logistics.print_on_demand` by autoconfiguration; `ConventionStock` calls every
one, once per order and SKU — see [Conventions](conventions.md).

| Configuration | What it sets |
|---|---|
| `documents.delivery_note_prefix`, `picking_slip_prefix`, `digits` | The series of the two documents in the core's register; `DN-`, `PS-` and 8 by default |
| `conventions.window_days` | How many days ahead a convention counts as near, 0 to 366; 30 by default |
| `conventions.online_channels` | The channels whose orders are placed online, beside orders without a channel |

## Repositories

Each repository port is a public alias of the module's Doctrine adapter; an application points it at a service of
its own under the same contract. `DoctrineLogisticsRepository` implements them all.

| Port | What a store of the application's own keeps |
|---|---|
| `StockRepository` | Every movement in the journal, `levelsOfSkus()` reading many SKUs at once |
| `LocationRepository` | The locations, and those open |
| `DeliveryMethodRepository` | The delivery methods, and those active |
| `ShipmentRepository` | The shipments of an order |
| `ShippingCostRepository` | One cost per shipment, and the costs of an order |
| `StockPolicyRepository` | Whether each SKU sells without stock |
| `ConventionRepository` | Conventions, the quantities prepared for them, `conventionsMeeting()` and `conventionSkus()` in one query each, and the units sent to print |
| `PurchaseOrderRepository` | Purchase orders, and those open |
| `SerialNumberRepository` | Serialised units, how many are in stock at a location, those of an order |
| `ShippingDocumentRepository` | The numbers of delivery notes and picking slips |
| `ShippingWaveRepository` | Shipping waves, those planned, and the orders of each |

## Services

| Service | How an application relies on it |
|---|---|
| `Stock` | Receives, counts, transfers and reserves; public — see [Stock](stock.md) |
| `Shipments` | Ships in one parcel or several, with carrier and tracking; public — see [Shipments](shipments.md) |
| `DeliveryOptions` | The delivery methods of a zone, their prices and estimated days; public — see [Delivery](delivery.md) |
| `ShippingLabels` | The carriers' rates and labels, the cost of each shipment, an order's margin; public — see [Carriers](carriers.md) |
| `ConventionStock` | The quantities prepared per convention, what is left to print, the route of an order placed online; public — see [Conventions](conventions.md) |
| `Purchasing` | Purchase orders and their receipts; public — see [Purchasing](purchasing.md) |
| `SerialNumbers` | The serial number of each unit; public — see [Purchasing](purchasing.md) |
| `ShippingDocuments` | Delivery notes and picking slips; public — see [Shipments](shipments.md) |
| `ShippingWaves` | Orders planned to ship together on an estimated day; public — see [Shipments](shipments.md) |

With `phil/commerce-returns-bundle`, a `Restocking` of the application's own puts returned units back in stock
through `Stock`.
