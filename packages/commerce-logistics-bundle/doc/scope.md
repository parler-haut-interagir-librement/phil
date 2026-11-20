# Scope

Logistics for the core of commerce: stock per SKU and location, its journal, receiving, counts, transfers and the
reservations of placed orders, with backorders per SKU; the stock of convention items and print on demand; purchase
orders and serial numbers; shipments in one parcel or several, their delivery notes and picking slips, and shipping
waves; delivery methods per zone; carriers' rates and labels with the cost of each shipment.

| Part | Content | State |
|---|---|---|
| Stock | Levels per SKU and location, the journal of every movement, receiving, counts, transfers, reservations of placed orders released or shipped, backorders per SKU, the stock of the core's deferred sales | Implemented and tested ([stock.md](stock.md)) |
| Conventions | Quantities prepared per convention and SKU, what is left to print, orders placed online sent to print while a convention is near | Implemented and tested ([conventions.md](conventions.md)) |
| Delivery | Methods per zone — shipping, local delivery, pickup —, their cost and free threshold, estimated days; charged by an `OrderProcessor` | Implemented and tested ([delivery.md](delivery.md)) |
| Purchasing | Purchase orders and receiving against them | Implemented and tested ([purchasing.md](purchasing.md)) |
| Carriers | Rates and labels through `CarrierGateway`, Canada Post's adapter, the cost of each shipment beside the delivery charged | Implemented and tested ([carriers.md](carriers.md)) |
| Shipments | An order shipped in one parcel or several, from its reservations, with carrier and tracking; delivery notes and picking slips; shipping waves | Implemented and tested ([shipments.md](shipments.md)) |
| Serial numbers | The serial number of each unit, kept with its stock | Implemented and tested ([purchasing.md](purchasing.md)) |

## Outside the module

- Supplier returns, replenishment and forecasts.
- Setting the delivery price from a carrier's rate, and taxing a delivery charge.
- Generating the SKUs and designs of printed items: the module reads the SKUs of the order's lines.

## The ports of the core it implements

- `OrderProcessor`, for delivery charges: `ShippingProcessor`, after the promotions and before the taxes.
- `StockCommitments`, for the stock of sales paid later: `DeferredStock`.
- `DocumentTypes`, for the delivery note and the picking slip: `LogisticsDocumentTypes`.
