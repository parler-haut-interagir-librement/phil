# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application implements, configures or relies on. The
ports of the core and their contracts are described there; messages are signed as Standard Webhooks by
`phil/standard-webhooks`.

## The core's ports it implements

| Port | Service | Tag |
|---|---|---|
| `SellableResolver` | `SyndicatedSellables`, for the type `syndicated` | `phil_commerce.sellable_resolver` |
| `SellableAvailability` | `SyndicatedSellables`: a product withdrawn or out of stock is hidden in every channel, and so refused | `phil_commerce.sellable_availability` |

See [Scope](scope.md).

## Its own ports

### `ForwardedOrderHandler`

`Phil\CommerceSyndicationBundle\Application\Port\ForwardedOrderHandler` — what a supplier does with an order a
reseller forwarded. A service implementing it is tagged `phil_commerce_syndication.forwarded_order_handler` by
autoconfiguration.

| Method | Contract |
|---|---|
| `handle(ForwardedOrder $order): void` | Prepares the order's shipment, most often; runs once per order, in the transaction that records the message |

### `PartnerShipmentHandler`

`Phil\CommerceSyndicationBundle\Application\Port\PartnerShipmentHandler` — what a reseller does once its supplier
shipped a parcel of a forwarded order. Tagged `phil_commerce_syndication.shipment_handler` by autoconfiguration.

| Method | Contract |
|---|---|
| `shipped(PartnerShipment $shipment): void` | Marks the order shipped, tells the buyer; runs once per parcel, in the transaction that records the message |

### `PartnerReturnHandler`

`Phil\CommerceSyndicationBundle\Application\Port\PartnerReturnHandler` — what each side does as a return moves.
Tagged `phil_commerce_syndication.return_handler` by autoconfiguration.

| Method | Contract |
|---|---|
| `requested(PartnerReturn $return): void` | On the supplier: a reseller announced the return; runs once per return |
| `received(PartnerReturn $return): void` | On the reseller: the supplier received and credited it; runs once per return |

### `MessageTransport`

`Phil\CommerceSyndicationBundle\Application\Port\MessageTransport` — carries one signed message to a partner.
`HttpClientTransport` posts over Symfony's HTTP client; an application aliases the port to its own.

| Method | Contract |
|---|---|
| `send(string $url, array $headers, string $body): int` | Posts the body with its headers as given and returns the HTTP status; `0` when no answer came — the endpoint unreachable, the connection cut, the time out |

## Repositories

`PartnerRepository`, `PublishedPriceRepository`, `LedgerRepository`, `MessageRepository`,
`SyndicatedProductRepository`, `PartnerShipmentRepository` and `PartnerReturnRepository` are public aliases of
`DoctrineSyndicationRepository`. `LedgerRepository::findEntry()` gives the entry of the order itself, never that of a
return, and `entriesOf()` all of them. A store of the application's own
keeps `MessageRepository::claim()` atomic, so that two deliveries never send one message, and `due()` bounded by its
limit.

## Configuration

| Option | What it extends |
|---|---|
| `identity` | This application's name at its partners |
| `signing_key` | Its `whsk_` Ed25519 secret key |
| `tolerance` | How far a message's timestamp may stray |
| `max_attempts`, `retention_days` | Deliveries, and how long messages are kept |
| `cost_grace_days` | How long a replaced cost still pays a forwarded line |

## Services and commands

`Partners`, `Publisher`, `Forwarder`, `Ledger`, `ShipmentTracking` and `PartnerReturns` are public;
`phil:commerce-syndication:deliver` and `phil:commerce-syndication:purge` run from a cron or a scheduler. See
[Syndication](syndication.md) and [Messages](messages.md).
