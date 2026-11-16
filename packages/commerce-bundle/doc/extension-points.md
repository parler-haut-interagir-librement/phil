# Extension points

Fifteen ports. Modules and the application plug into the nine that autoconfiguration tags, which `OrderModifier`,
`Payments`, `LineOptions`, `OptionDraws`, `DeferredSales` and `DocumentRegister` read; the other six are aliases,
which the application points to its own adapters or leaves on the bundle's (see [installation.md](installation.md)).
`StockCommitments`, `DocumentTypes`, `DocumentSeries`, `TransitionForcing` and `OrderShipments` live in
`Phil\CommerceBundle\Application\Contract`, the others in `Phil\CommerceBundle\Application\Port`. The storage
contracts of the domain, the events and what a returns module builds on follow them.

| Port | Tag | Used by | Purpose |
|---|---|---|---|
| `SellableResolver` | `phil_commerce.sellable_resolver` | `OrderModifier`, at each line added or changed | What a reference sells |
| `UnitPriceResolver` | `phil_commerce.unit_price_resolver` | `OrderModifier`, at each line priced | A unit price other than the base price |
| `OrderProcessor` | `phil_commerce.order_processor` | `OrderModifier`, after each change | Recomputes one type of adjustment |
| `PaymentGateway` | `phil_commerce.payment_gateway` | `Payments` | One payment processor |
| `SellableAvailability` | `phil_commerce.sellable_availability` | `OrderModifier`, at each line added and each change of channel; `SellableOffers`, for the pages | What a channel offers of something |
| `SellableOptions` | `phil_commerce.sellable_options` | `LineOptions`, for `OrderModifier` and `OptionSupplementProcessor` | The options of what is sold |
| `OptionDrawer` | `phil_commerce.option_drawer` | `OptionDraws`, once the order is paid | Draws an option's value for one unit |
| `StockCommitments` | `phil_commerce.stock_commitments` | `DeferredSales`, when a deferred sale holds or frees its stock | Holds and frees the stock of an order paid later |
| `TaxRateResolver` | — (alias; `ConfiguredTaxRates` by default) | `TaxOrderProcessor` | Which rates apply to a sale |
| `PaymentAttemptStateStore` | — (alias) | the application, around `PaymentAttemptLimiter` | One limiter state per key, with a time-to-live |
| `DocumentNumbering` | — (alias; `DoctrineDocumentNumbering` with DoctrineBundle) | `Receipts` | The next number of a series of documents |
| `DocumentTypes` | `phil_commerce.document_types` | `DocumentRegister` | Declares document types to the register |
| `DocumentSeries` | — (alias; `DoctrineDocumentNumbering` with DoctrineBundle) | `DocumentRegister` | The next number of a type's series |
| `TransitionForcing` | — (alias; `DenyForcing` by default) | `OrderLifecycle::force()` | Who may force an order outside its transitions |
| `OrderShipments` | — (alias; `UnknownShipments` by default) | the modules, a returns module first | When an order, or one of its lines, left the store |

## `SellableResolver`

| Method | Contract |
|---|---|
| `supports(string $type): bool` | Whether the resolver answers for this type of reference |
| `resolve(SellableReference $reference): ?Sellable` | What the reference sells, or `null` when nothing is known by its identifier |

Invariants:

- **One resolver per type.** The first that supports a type answers alone; a type two resolvers claim is a
  configuration mistake that the order of services would settle silently.
- The base unit price and the tax category come from the module's own data, never from the request.
- `null` makes `OrderModifier` throw `UnknownSellable`: a product withdrawn from sale is not sellable.

```php
namespace App\Commerce;

use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Money;

final readonly class GiftCards implements SellableResolver
{
    public function supports(string $type): bool
    {
        return 'gift_card' === $type;
    }

    public function resolve(SellableReference $reference): ?Sellable
    {
        $amount = $this->denominations->find($reference->id); // the application's own data

        return null === $amount ? null : Sellable::of($reference, 'Gift card', $amount, 'exempt');
    }
}
```

## `UnitPriceResolver`

| Method | Contract |
|---|---|
| `priceFor(Sellable $sellable, PricingContext $context): ?Money` | The unit price in the sellable's currency, or `null` when this resolver has no price for it in this context |

Invariants:

- Resolvers are asked in the order of their tags, which name the resolvers they come before or after with
  `before` and `after`; the first price given wins, otherwise the base price applies.
- The context's quantity is the line's whole quantity, so that a quantity tier applies to the line, not to the
  units just added.
- Return a price of zero or more; a discount on a price is a `Promotion` adjustment.

## `OrderProcessor`

| Method | Contract |
|---|---|
| `process(Order $order): void` | Recomputes the adjustments of the processor's own type with `Order::replaceAdjustments()` |

Invariants:

- **One type per processor, replaced whole**: running it twice changes nothing.
- Processors run in the order of their tags, which name the processors they run before or after with `before`
  and `after`. Promotions run before `TaxOrderProcessor`, so that taxes are computed on discounted lines; shipping
  and fees may run on either side.
- A processor never changes lines or prices; it only writes adjustments.

## `SellableAvailability`

| Method | Contract |
|---|---|
| `offerThrough(SellableReference $reference, ?string $channel): Offer` | How far the channel shows it — `Listed`, `Unlisted` for its direct link only, `Hidden` —, whether an order of the channel may hold it, and whether its price is shown |

Invariants: answer `Offer::full()` for what the implementation does not know, so that several selections add
up, the strictest answer winning; an order without a channel is offered only what every channel offers. The reference
comes without a line's configuration.

## `SellableOptions`

| Method | Contract |
|---|---|
| `supports(string $type): bool` | Whether it declares the options of this type of reference |
| `optionsOf(SellableReference $reference, Currency $currency): OptionSet` | The sellable's options, its supplements in the currency given; `OptionSet::none()` for none |

Invariants: one implementation per type, the first that supports it answering alone; the reference comes without a
line's configuration; the same reference gives the same options within a request, since `OrderModifier` checks the
configuration and `OptionSupplementProcessor` charges it from two calls ([options.md](options.md)).

## `OptionDrawer`

| Method | Contract |
|---|---|
| `supports(string $drawer): bool` | Whether it draws for the drawer an option names |
| `draw(string $drawer, Order $order, OrderLine $line, string $option, int $unit): string` | The draw for one unit of the line, numbered from 1: 1 to 1000 characters |

Invariants: called only for a paid order, once per unit and per option; the result is kept with the line and never
asked again. A drawer whose buyers may verify its draws keeps its own proof, tied to the order, the line and the
unit it is given.

## `PaymentGateway`

| Method | Contract |
|---|---|
| `supports(string $method): bool` | Whether the gateway answers for this method; exactly one should |
| `start(Payment $payment, Order $order): PaymentStart` | Hands the payment to the processor for `$payment->amount`; returns the processor's reference and a page or a client secret |
| `refund(Payment $payment, Money $amount, string $idempotencyKey): void` | Asks the processor for a refund, handing it the key as its idempotency key; records nothing |
| `read(string $payload, array $headers): PaymentNotification` | Verifies the processor's signature and reads its event; throws `InvalidNotification` otherwise |

Invariants:

- **Verify before reading**: the signature, with a comparison in constant time, and the notification's age when
  the processor signs a timestamp. A notification is trusted for its signature only.
- **Name the payment by the processor's own reference**, the one `start()` returned, and the notification by the
  processor's own event id: the payment applies an id once.
- **Map the processor's events onto `PaymentEvent`**; an event the store does not act on is read as
  `InvalidNotification`, which the webhook answers 400, or simply not subscribed to.
- **Never take an amount from the request**: `start()` charges the payment's amount, `refund()` the amount asked.
- **Hand the idempotency key to the processor**: the same key comes with every try of one refund, so that the
  processor makes it once.
- Keep the processor's credentials in the application's secrets, read by the adapter.

## `StockCommitments`

`Phil\CommerceBundle\Application\Contract\StockCommitments` holds and frees the stock of an order paid later
([deferred-sales.md](deferred-sales.md)). `DeferredSales` calls every tagged service, before the events are
dispatched; with none, the stock is followed by the events alone.

| Method | Contract |
|---|---|
| `commit(string $orderNumber): void` | Holds the order's goods for its buyer: when the sale opens with `StockCommitment::OnOrder`, once settled with `OnBalance` |
| `release(string $orderNumber): void` | Frees what `commit()` held: the deferred sale was cancelled before it was paid in full |

Invariants:

- **Called once per sale and direction**: a sale commits its stock once and releases it at most once.
- The order's number is all it receives: the implementation reads the lines from its own store, never from a request.
- It runs inside the application's unit of work, before the commit: it schedules its writes in the same transaction,
  and leaves anything sent outside to a listener after the commit.

```php
namespace App\Logistics;

use Phil\CommerceBundle\Application\Contract\StockCommitments;

final readonly class Reservations implements StockCommitments
{
    public function commit(string $orderNumber): void
    {
        $this->stock->reserveFor($orderNumber); // the application's own stock
    }

    public function release(string $orderNumber): void
    {
        $this->stock->freeFor($orderNumber);
    }
}
```

## `TaxRateResolver`

| Method | Contract |
|---|---|
| `ratesFor(string $taxCategory, string $zone): list<Phil\Tax\TaxRate>` | Every rate that applies to a sale of this category to a buyer in this zone, each on the same base; an empty list when none applies |

Invariants:

- Category and zone are names the application chooses (`digital`, `QC`…); the bundle gives them no meaning.
- Return each rate code once: `TaxBreakdown` refuses a code that appears twice.
- Rates that compound (a tax on the base plus another tax) cannot be expressed: every rate applies to the same
  base.
- Thresholds, registrations and place of supply are decided here or by the engine behind the adapter, never by the
  bundle.

The bundle's own `ConfiguredTaxRates` answers from `phil_commerce.tax_rates`. An application whose rates come from
elsewhere — an external tax engine, rates kept in its database — aliases its own adapter, which replaces it:

```php
namespace App\Commerce;

use Phil\CommerceBundle\Application\Port\TaxRateResolver;
use Phil\Tax\TaxRate;

final readonly class StoredTaxRates implements TaxRateResolver
{
    public function __construct(private TaxRateRepository $repository) // the application's own storage
    {
    }

    public function ratesFor(string $taxCategory, string $zone): array
    {
        $rates = [];

        foreach ($this->repository->percentagesFor($taxCategory, $zone) as $code => $percent) {
            $rates[] = TaxRate::ofPercent($code, $percent);
        }

        return $rates;
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\CommerceBundle\Application\Port\TaxRateResolver: '@App\Commerce\StoredTaxRates'
```

An adapter over an external tax engine may instead build the breakdown from the engine's amounts with
`TaxLine::reported()` and `TaxBreakdown::of()` of `phil/tax`, so that the receipt itemises what the engine computed
(see [taxes.md](taxes.md)).

## `PaymentAttemptStateStore`

| Method | Contract |
|---|---|
| `find(PaymentAttemptKey $key): ?PaymentAttemptState` | The stored state, or `null` when there is none or it has expired |
| `save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void` | Store the state under `$key->value`, replacing any previous one, and **make it expire after `$timeToLiveSeconds`** |

Invariants:

- **Honour the time-to-live.** A client IP address is personal information, and the limiter's window is what bounds
  how long it is kept. A store with native expiry (a key-value store, a cache pool with per-item lifetime) is the
  natural fit.
- **Store exactly the state given**: its three fields. No verdict, no penalty counter, no history, no log of keys.
- Rebuild the state with `PaymentAttemptState::of()` when reading it back.

```php
namespace App\Commerce;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptKey;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachePaymentAttemptStateStore implements PaymentAttemptStateStore
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    public function find(PaymentAttemptKey $key): ?PaymentAttemptState
    {
        $item = $this->pool->getItem($this->id($key));

        if (!$item->isHit()) {
            return null;
        }

        [$attempts, $wait, $last] = $item->get();

        return PaymentAttemptState::of($attempts, $wait, new DateTimeImmutable($last));
    }

    public function save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void
    {
        $item = $this->pool->getItem($this->id($key));
        $item->set([$state->attempts, $state->cumulativeWaitSeconds, $state->lastAttemptAt->format('Y-m-d\TH:i:s.uP')]);
        $item->expiresAfter($timeToLiveSeconds);
        $this->pool->save($item);
    }

    private function id(PaymentAttemptKey $key): string
    {
        // PSR-6 forbids some characters in keys (":" among them); hash the key.
        return 'payment_attempt.'.hash('sha256', $key->value);
    }
}
```

## `DocumentNumbering`

| Method | Contract |
|---|---|
| `next(DocumentKind $kind): string` | The next number of the kind's series, formatted |

Invariants: **sequential and without gaps** — a number given is used, or given back with the transaction that took
it; two calls at once never return the same number; each kind has its own series. `DoctrineDocumentNumbering`
does this with a row per series locked for writing, read again from the database once locked, and refuses to run
outside a transaction. A series' first row is created on its own, in a savepoint, so two first issues at once do
not fail; a migration may also create the rows beforehand.

## `DocumentTypes`

| Method | Contract |
|---|---|
| `documentTypes(): iterable<DocumentType>` | The types the module declares, each a code, a prefix, digits and a visibility |

Invariants: a code and a prefix no other type uses; the register refuses a duplicate on its first read. A logistics
module declares its packing slip this way:

```php
final readonly class LogisticsDocuments implements DocumentTypes
{
    public function documentTypes(): iterable
    {
        yield DocumentType::of('packing_slip', 'PS-', 8);                         // kept for the store
        yield DocumentType::of('delivery_note', 'DN-', 8, Visibility::Unlisted); // reached by the link of its email
    }
}
```

## `DocumentSeries`

| Method | Contract |
|---|---|
| `nextIn(DocumentType $type): string` | The next number of the type's series, formatted by `DocumentType::format()` |

Invariants: those of `DocumentNumbering`, one series per type's code; `DoctrineDocumentNumbering` implements both
under the same lock.

## `TransitionForcing`

| Method | Contract |
|---|---|
| `allows(Order $order, OrderState $to, string $actor): bool` | Whether the actor may force the order to that state |

Invariants: answer from the application's rights, never from the request; refuse by default. An adapter over the
security voters of the application:

```php
final readonly class VoterForcing implements TransitionForcing
{
    public function __construct(private AuthorizationCheckerInterface $authorization)
    {
    }

    public function allows(Order $order, OrderState $to, string $actor): bool
    {
        return $this->authorization->isGranted('ROLE_ORDER_FORCE', $order);
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\CommerceBundle\Application\Contract\TransitionForcing: '@App\Commerce\VoterForcing'
```

## `OrderShipments`

| Method | Contract |
|---|---|
| `shippedAt(string $orderNumber, ?SellableReference $line = null): ?DateTimeImmutable` | When the order's goods were shipped or handed over — the line's when a line is given —; `null` when they have not left, or the implementation does not know |

Invariants: answer from the store's own records, never from a request; a line shipped in several parcels answers the
last of them, when the whole line has left; an order answers when its last line has left. The bundle keeps no
shipment: its `UnknownShipments` answers `null` for every order, so that a module counting a window from the
shipment falls back on its own rule. A logistics module, or the application, aliases its own:

```php
namespace App\Logistics;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Contract\OrderShipments;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

final readonly class ParcelShipments implements OrderShipments
{
    public function __construct(
        private Parcels $parcels, // the application's own parcels
    ) {
    }

    public function shippedAt(string $orderNumber, ?SellableReference $line = null): ?DateTimeImmutable
    {
        return $this->parcels->lastShippedAt($orderNumber, $line?->value);
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\CommerceBundle\Application\Contract\OrderShipments: '@App\Logistics\ParcelShipments'
```

## Storage contracts of the new records

Each is a contract of `Phil\CommerceBundle\Domain\Contract`, aliased to its Doctrine adapter with DoctrineBundle;
an application that stores them elsewhere aliases its own.

| Contract | Methods | Doctrine adapter |
|---|---|---|
| `OrderJournal` | `record()`, `movesOf($orderNumber, $limit)` | `DoctrineOrderJournal` |
| `WishListRepository` | `findWishList()`, `findSharedWishList($tokenHash)`, `wishListsOf($owner, $limit)`, `addWishList()`, `removeWishList()` | `DoctrineWishListRepository` |
| `PaymentLinkRepository` | `findPaymentLinkByTokenHash()`, `paymentLinksOf($orderNumber)`, `addPaymentLink()` | `DoctrinePaymentLinkRepository` |
| `OrderChangeRepository` | `findOrderChange()`, `changesOf($orderNumber)`, `addOrderChange()` | `DoctrineOrderChangeRepository` |
| `RefundRepository` | `findRefund()`, `refundsOf($paymentId)`, `addRefund()` | `DoctrineRefundRepository` |
| `ExpiringOrders` | `unpaidPlacedBefore()`, `expiredBefore()`, `deleteExpired()` | `DoctrineOrderRepository` |

Invariants: `add*()` and `record()` only schedule, the application commits; lists come oldest first and bounded;
`deleteExpired()` checks again, in its transaction, that no payment ever held money and that no receipt exists.

## Events

| Event | Dispatched by | When |
|---|---|---|
| `OrderPurchased` | `Payments` | Once, when an order becomes paid in full ([payments.md](payments.md#an-order-paid-in-full)) |
| `OrderRefunded` | `Refunds` | Once per refund executed ([refunds.md](refunds.md)) |
| `OrderCancelled` | `OrderLifecycle` | Once per cancellation: cancelled, expired or forced to `cancelled` ([lifecycle.md](lifecycle.md#announcing-a-cancellation)) |
| `OrderStateForced` | `OrderLifecycle` | When someone forces an order outside its transitions ([lifecycle.md](lifecycle.md#forcing-a-move)) |
| `workflow.phil_commerce_order.*` | the order workflow | At each transition, as `symfony/workflow` names them ([lifecycle.md](lifecycle.md)) |

Each is dispatched by its class and carries identifiers only — the order's number, its channel, its customer's
stable identifier, the moment, and the amount for a purchase or a refund —, never a name, an address or a line; a
listener reads what else it needs from its own store, and one that hands the event to Messenger sends those
identifiers alone.

| Event | Fields |
|---|---|
| `OrderPurchased` | `orderNumber`, `channel`, `customer`, `total`, `occurredAt()` |
| `OrderRefunded` | `orderNumber`, `refundId`, `paymentId`, `channel`, `customer`, `amount`, `occurredAt()` |
| `OrderCancelled` | `orderNumber`, `from`, `channel`, `customer`, `expired`, `actor`, `occurredAt()` |

### Recording purchases in an activity feed

An application that keeps an activity feed — a member's history, points, badges — records a purchase from
`OrderPurchased` and withdraws it from `OrderRefunded` or `OrderCancelled`, keyed by the order's number so that a
second delivery of the same event changes nothing:

```php
namespace App\Activity;

use Phil\CommerceBundle\Domain\Event\OrderCancelled;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Event\OrderRefunded;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class PurchaseActivity
{
    public function __construct(
        private ActivityFeed $feed, // the application's own feed
    ) {
    }

    #[AsEventListener]
    public function onPurchased(OrderPurchased $event): void
    {
        if (null !== $event->customer) {
            $this->feed->record($event->customer, 'order.paid', 'order:'.$event->orderNumber.':paid', $event->occurredAt());
        }
    }

    #[AsEventListener]
    public function onRefunded(OrderRefunded $event): void
    {
        $this->feed->cancel('order:'.$event->orderNumber.':paid', $event->occurredAt()); // or only on a refund in full
    }

    #[AsEventListener]
    public function onCancelled(OrderCancelled $event): void
    {
        $this->feed->cancel('order:'.$event->orderNumber.':paid', $event->occurredAt());
    }
}
```

`OrderRefunded` carries the amount refunded, not whether the order is now refunded in full: a listener that withdraws
only on a refund in full compares it with what it recorded, or reads the order's payments with `Payments::summary()`.

## What a returns module builds on

A returns module — return requests, their window and states, the return slip — keeps its own records and plugs into
the core through these:

| Need | What the core offers |
|---|---|
| The window counted from the shipment | `OrderShipments::shippedAt()`, per order or per line; `Order::placedAt()` and `OrderPurchased` when no shipment is known |
| The refund of what came back | `Refunds::grant()` and `execute()`, with the return's reason; `OrderRefunded` once executed ([refunds.md](refunds.md)) |
| The credit note of what came back | `Receipts`, for the whole receipt ([receipts.md](receipts.md)); a partial-quantity credit is not started |
| A return slip numbered without gaps | A `DocumentTypes` service declaring its type — `return_slip`, visibility `unlisted` —, numbered by `DocumentRegister::next()`, its PDF laid out with `phil/paged-media` ([documents.md](documents.md)) |
| An order no longer returnable | `OrderCancelled`, and the `cancel` and `expire` transitions of the workflow (`workflow.phil_commerce_order.completed.expire`); `OrderExpiry` sweeps only unpaid orders |
| Who the buyer is | `Order::customer()`, the customer's stable identifier, and `Order::channel()` |

The expiry of a return request, its rate limit and the states it waits in are the module's own: the core knows no
return.

## `ReceiptRepository`

| Method | Contract |
|---|---|
| `find(string $number): ?Receipt` | The receipt or credit note |
| `receiptOf(string $orderNumber): ?Receipt` | The receipt of an order; never a credit note |
| `creditNotesOf(string $receiptNumber): list<Receipt>` | The credit notes of a receipt, oldest first |
| `add(Receipt $receipt): void` | Schedules it to be stored with the application's next commit |

## `PaymentRepository`

`Phil\CommerceBundle\Domain\Repository\PaymentRepository` is the domain's contract for storing payments; with
DoctrineBundle the bundle aliases it to `DoctrinePaymentRepository`, and `Payments` receives it by autowiring.

| Method | Contract |
|---|---|
| `find(string $id): ?Payment` | The stored payment, or `null` |
| `findByExternalReference(string $method, string $externalReference): ?Payment` | The payment a notification names |
| `forOrder(string $orderNumber): list<Payment>` | Every payment of the order, oldest first |
| `add(Payment $payment): void` | Schedules the payment to be stored with the application's next commit |

## `OrderRepository`

`Phil\CommerceBundle\Domain\Repository\OrderRepository` is the domain's contract for storing orders; with
DoctrineBundle the bundle aliases it to `DoctrineOrderRepository`. An application that stores orders elsewhere
aliases its own adapter instead.

| Method | Contract |
|---|---|
| `find(string $number): ?Order` | The stored order, or `null` |
| `add(Order $order): void` | Schedules the order to be stored with the application's next commit |

Invariants: store the order whole, lines and adjustments included; refuse a write over a stale copy, as the
version column does; rebuild lines and adjustments through their own constructors when reading.

## Value objects and services

Every other public type is a `final` class: use them, do not extend them. `PaymentAttemptLimiter` holds only a
policy, so declare it as a service or construct it where needed; `OrderModifier` comes from the container.
