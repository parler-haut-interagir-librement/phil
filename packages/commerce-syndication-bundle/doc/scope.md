# Scope

Catalogue syndication between distinct applications of the core of commerce.

| Part | Content | State |
|---|---|---|
| Partners | Suppliers and resellers, their endpoints and public keys, suspension | Implemented and tested ([syndication.md](syndication.md)) |
| Feed | Price, cost, stock and withdrawal of each product, to each reseller fed it | Implemented and tested |
| Syndicated products | A supplier's products sold here, as the core's sellables | Implemented and tested |
| Forwarding | Each order's syndicated lines sent to their suppliers, once | Implemented and tested |
| Ledger | What each partner owes per order, balances, settlement | Implemented and tested |
| Messages | Standard Webhooks signed with Ed25519, an outbox with retries, deduplication on receipt | Implemented and tested ([messages.md](messages.md)) |
| Shipment tracking | The supplier telling the reseller each parcel of the order, with its carrier and tracking number | Implemented and tested ([syndication.md](syndication.md)) |
| Returns | A return at the reseller carried to the supplier, and its credit in both ledgers | Implemented and tested ([syndication.md](syndication.md)) |
| Rich content | Descriptions, images and attributes in the feed | Not started |
| Initial sync | Sending a reseller the whole feed when it is first fed | Not started: the application publishes each SKU |

## The ports of the core it implements

- `SellableResolver`: `SyndicatedSellables`, for the type `syndicated`.
- `SellableAvailability`: `SyndicatedSellables`, which hides a product withdrawn or out of stock in every channel,
  and so refuses it.

## Its own ports

- `MessageTransport`: carries a signed message; `HttpClientTransport` over Symfony's HTTP client.
- `ForwardedOrderHandler`: what the supplier does with an order a reseller forwarded.
- `PartnerShipmentHandler`: what the reseller does once its supplier shipped a parcel.
- `PartnerReturnHandler`: what each side does as a return is requested and received.
