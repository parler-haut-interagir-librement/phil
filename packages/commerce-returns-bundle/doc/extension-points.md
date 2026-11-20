# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application implements, configures or relies on. The
ports of the core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag |
|---|---|---|
| `PaymentGateway` | `ReturnsGateway`, for the method `exchange` | `phil_commerce.payment_gateway` |
| `DocumentTypes` | `ReturnDocumentTypes`, the series `return_slip` | `phil_commerce.document_types`, by autoconfiguration |

See [Scope](scope.md).

## Its own ports

### `Restocking`

`Phil\CommerceReturnsBundle\Application\Port\Restocking` — puts back on the shelf what came back resellable. A service
implementing it is tagged `phil_commerce_returns.restocking` by autoconfiguration; every one runs when a return is
received, in the transaction that records it.

| Method | Contract |
|---|---|
| `restock(SellableReference $line, int $quantity, string $return, DateTimeImmutable $at): void` | Puts the units back — a stock movement of `phil/commerce-logistics-bundle`, or the application's own; `$line` is the order line's reference, and what goes back on the shelf is its `unconfigured()` sellable |

### `StoreCreditGranter`

`Phil\CommerceReturnsBundle\Application\Port\StoreCreditGranter` — gives a customer store credit for a return.
`StoredValueStoreCredit` uses `phil/commerce-stored-value-bundle` when it is registered; an application aliases the
port to its own otherwise.

| Method | Contract |
|---|---|
| `grant(string $customer, Money $amount, DateTimeImmutable $at, string $note, string $orderNumber): void` | Credits the customer with the amount, in the order's currency |

### `SetParts`, `SpecialDiscounts`, `ShipmentDates`

`Phil\CommerceReturnsBundle\Application\Contract\…` — what other modules know of an order. Each is served by an
adapter the module registers when the module that knows is registered; an application aliases the port to its own
service otherwise.

| Port | Method | Adapter |
|---|---|---|
| `SetParts` | `partsOf(SellableReference $reference): list<string>` — the parts one unit of a set holds, as order lines would name them | `CatalogSetParts`, on `phil/commerce-catalog-bundle` |
| `SpecialDiscounts` | `isSpecial(Adjustment $adjustment): bool` — whether a promotion adjustment is a special's | `PromotionSpecials`, on `phil/commerce-promotions-bundle` |
| `ShipmentDates` | `lastShippedAt(string $orderNumber): ?DateTimeImmutable` | `LogisticsShipmentDates`, on `phil/commerce-logistics-bundle` |

### `ReturnLabels`

`Phil\CommerceReturnsBundle\Application\Contract\ReturnLabels` — asks a carrier for the label of an authorized
return. The application implements it — an adapter per carrier — and aliases the port; `Returns::label()` is refused
without one.

| Method | Contract |
|---|---|
| `labelFor(ReturnRequest $return, DateTimeImmutable $at): ReturnLabel` | The carrier, the tracking number, and the adapter's own reference to the label's file |

### `ReturnSlipRenderer`

`Phil\CommerceReturnsBundle\Application\Contract\ReturnSlipRenderer` — draws a slip as a PDF.
`PagedMediaReturnSlips` serves it when `phil/paged-media` is installed; an application aliases the port to its own
renderer to draw its own slip.

| Method | Contract |
|---|---|
| `render(ReturnSlip $slip, ?string $locale = null): string` | The PDF's bytes, in the locale given |

## Repositories

`ReturnRepository` is a public alias of `DoctrineReturnRepository`; an application points it at a service of its own
under the same contract.

## Configuration

| Option | What it extends |
|---|---|
| `window_days` | How many days after an order is placed — or last shipped — its goods may come back; 30 by default |
| `window_from` | `placed`, the default, or `shipped`: the order's last shipment, when the logistics module knows one |
| `restocking_fee` | The share kept on the lines the shop is not at fault for, in parts per million |
| `delivery_refund` | `never`, `shops_fault` (the default) or `whole_order`: when the delivery charge comes back |
| `expiry_days` | How many days an authorized return waits for its goods before `expire()` expires it; `null`, the default, for ever |
| `rate_limiter` | The service of a `symfony/rate-limiter` factory that bounds the returns each customer asks for; `null`, the default, for none |
| `slips.prefix`, `slips.digits` | The prefix and the digits of the slips' numbers; `RS-` and 8 by default |

A rate limit counts in the store its factory names: behind a load balancer, a store shared by every server, so that a
customer cannot spend the limit once per server.

## Events

`ReturnStateChanged`, `ReturnSettled` and `ReturnCreditNoteIssued` are dispatched by their class through
`event_dispatcher`, when there is one: an application listens with `#[AsEventListener]` or the tag
`kernel.event_listener` — see [Returns](returns.md#events).

## Translations

The words of the slip are the keys `phil_commerce_returns.slip.*` and `phil_commerce_returns.reason.*` of the domain
`phil_commerce_returns`, translated in `translations/phil_commerce_returns.en.php` and `.fr.php`; an application
overrides them in its own translations.

## Services

| Service | How an application relies on it |
|---|---|
| `Returns` | Requests, steps, settling, expiry, labels, anonymization; public — see [Returns](returns.md) |
| `ReturnCreditNotes` | Issues the credit note of a return; public — see [Credit notes and slips](credit-notes-and-slips.md) |
| `ReturnSlips` | The slip of an authorized return, and its PDF; public, registered when a renderer is |
