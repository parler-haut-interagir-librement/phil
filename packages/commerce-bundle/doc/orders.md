# Orders

An order is built line by line, then its adjustments are recomputed by the order processors. The core holds the
order and the ports; what is sold and at what price comes from the modules and the application.

| Type | Role |
|---|---|
| `Domain\Model\Order` | The order: number, currency, lines, adjustments, tax zone, state; totals always computed |
| `Domain\Order\OrderState` | `Cart`, `Quoted`, `Placed`, `Cancelled` |
| `Domain\Repository\OrderRepository` | Finds and stores orders; `DoctrineOrderRepository` with DoctrineBundle |
| `Domain\ValueObject\OrderLine` | One line: what is sold, quantity, unit price, tax category, configuration ([options.md](options.md)) |
| `Domain\ValueObject\SellableReference` | What a line sells: a type and an identifier, `product:42`, and a configured line's fingerprint |
| `Domain\ValueObject\Sellable` | What a module says about a reference: description, base unit price, tax category, options |
| `Domain\ValueObject\PricingContext` | What a price may depend on: quantity, moment, customer group, channel |
| `Domain\ValueObject\Adjustment` | An amount on the order or a line, with its label; neutral or not; its origin, as its processor names it |
| `Domain\Adjustment\AdjustmentType` | `Option`, `Promotion`, `Proration`, `Tax`, `Shipping`, `Fee`, `Tip` |
| `Application\OrderModifier` | Changes an order through the ports and recomputes it |

## Example

```php
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;

$order = Order::open('2026-0001', Currency::of('CAD'));
$context = PricingContext::of(1, $clock->now(), customerGroup: 'wholesale', channel: 'web');

$modifier->add($order, SellableReference::of('product', 'dice'), 2, $context); // OrderModifier, from the container
$modifier->changeTaxZone($order, 'QC');

$order->itemsTotal();        // the lines, at the prices the resolvers gave
$order->adjustmentsTotal();  // promotions, taxes, shipping, fees, tips that are not neutral
$order->total();

$modifier->place($order, $clock->now());   // recomputed once more, then frozen
$orders->add($order);                       // OrderRepository; the application commits its unit of work
```

## `OrderModifier`

| Method | Behaviour |
|---|---|
| `add(Order, SellableReference, int $quantity, PricingContext, ?LineConfiguration): OrderLine` | Resolves the sellable and its options, checks the configuration, prices the line for its whole new quantity, adds it, recomputes; returns the line |
| `changeQuantity(Order, SellableReference, int $quantity, PricingContext)` | Prices the line again for the new quantity, recomputes |
| `remove(Order, SellableReference)` | Removes the line and its adjustments, recomputes |
| `changeTaxZone(Order, ?string $zone)` | Sets the zone the tax rates are read for, recomputes |
| `changeAudience(Order, ?string $channel, ?string $customerGroup, PricingContext)` | Sets who the order is sold through and to, prices every line again for them, recomputes |
| `chooseDeliveryMethod(Order, ?string $method)` | Records the delivery method the buyer chose, recomputes |
| `enterCode(Order, string $code)`, `removeCode(Order, string $code)` | Records or drops a promotion code the buyer entered, recomputes |
| `place(Order, DateTimeImmutable $at)` | Recomputes the cart one last time, then places it |
| `recalculate(Order)` | Runs every processor again, after a change the modifier did not make |

- **Prices come from the resolvers, never from the caller.** A request names what to buy and how many; the
  server decides what it costs.
- **The order's channel must sell it**: every `SellableAvailability` is asked, on adding a line and on
  `changeAudience()`; a sellable no order of the channel may hold throws `NotSoldThere` before anything
  changes, and one its direct link alone reaches is sold as anything listed ([channels.md](channels.md)).
- **The first sellable resolver that supports the reference's type answers**; if it knows nothing by that
  identifier, `UnknownSellable` is thrown, and no other resolver is asked.
- **The unit price** is the first one a `UnitPriceResolver` gives, in the order of their tags, asked with the line's
  whole quantity and the order's channel and customer group — unless the caller's `PricingContext` names its own —;
  otherwise the sellable's base price.
- **The options come from the first `SellableOptions` of the reference's type**, through `LineOptions`, and check the
  configuration: `InvalidConfiguration` before anything changes ([options.md](options.md)).
- **Resolvers, prices and selections are asked with the reference `unconfigured()`**: what is sold, without the
  configuration of a line.
- **Every processor runs after each change**, in the order of their tags.

## `Order`

| Member | Behaviour |
|---|---|
| `static open(string $number, Currency $currency): self` | Number: 1 to 64 visible ASCII characters |
| `add(Sellable, int $quantity, Money $unitPrice, ?LineConfiguration): OrderLine` | Adds a line, or adds to the line that sells the same thing with the same option values, at the new unit price; the sellable's options check the configuration |
| `changeQuantity(SellableReference, int $quantity, Money $unitPrice)` | `LineNotInOrder` when the order has no such line |
| `remove(SellableReference)` | Removes the line and the adjustments on it |
| `recordDraws(SellableReference, string $option, list<string> $draws)` | A placed order only: the draws of an option left to draw, one per unit |
| `line(SellableReference): ?OrderLine`, `lines(): list<OrderLine>`, `isEmpty()` | The lines, in the order they were first added |
| `taxZone(): ?string`, `changeTaxZone(?string)` | The zone the tax rates are read for; `null` until known |
| `channel(): ?string`, `changeChannel(?string)` | The channel that sells — a site, a point of sale —; 1 to 64 visible ASCII characters |
| `customerGroup(): ?string`, `changeCustomerGroup(?string)` | The buyer's customer group; 1 to 64 visible ASCII characters |
| `deliveryMethod(): ?string`, `chooseDeliveryMethod(?string)` | The delivery method chosen, as the logistics module names it |
| `codes(): list<string>`, `enterCode(string)`, `removeCode(string)` | The promotion codes entered, each once; whether one is worth anything is the promotions' decision |
| `replaceAdjustments(AdjustmentType, list<Adjustment>)` | Replaces every adjustment of that type; refused, without change, when one is of another type, in another currency or on a line the order lacks |
| `adjustments(?AdjustmentType)`, `lineAdjustments(SellableReference, ?AdjustmentType)` | The adjustments, all or of one type, on the order or on one line |
| `adjustmentsByLine(?AdjustmentType)` | The adjustments on lines, all or of one type, keyed by the line's reference value, read in one pass: what a processor visiting every line uses instead of one `lineAdjustments()` per line |
| `itemsTotal()`, `adjustmentsTotal(?AdjustmentType)`, `total()` | Computed each time; neutral adjustments are left out of the sums |
| `taxIncluded()`, `changeTaxIncluded(bool)` | Whether the prices include the taxes; a cart only |
| `includedTax()` | The neutral tax adjustments: the taxes the prices include |
| `state()`, `placedAt()`, `cancelledAt()` | Where the order stands, and when it moved |
| `quote()` | A cart with at least one line becomes quoted: frozen until it is placed or reopened |
| `place(DateTimeImmutable $at)` | A cart or a quote with at least one line becomes placed |
| `reopen()` | A quote or a placed order becomes a cart again, to be changed; a placed order forgets its moment |
| `cancel(DateTimeImmutable $at)` | A cart, a quote or a placed order becomes cancelled |
| `version(): int` | The stored version, which each write moves on |

- **One currency per order.** A price or an adjustment in another currency throws `CurrencyMismatch`.
- **A line holds at least one unit, at a unit price of zero or more.** A discount is an adjustment, never a
  negative price.
- **A neutral adjustment is shown but not added**: a tax already included in the price, for instance.
- **An adjustment may keep its origin**, what gave it as its processor names it (`promotion:black-friday`): never
  shown, it lets a module read after placement what the order got, rather than compute it again from rules that
  may have changed since.

## States

| From | To | By |
|---|---|---|
| `Cart` | `Quoted` | `quote()`, when the cart has a line |
| `Cart` | `Placed` | `place()`, when the cart has a line |
| `Cart` | `Cancelled` | `cancel()` |
| `Quoted` | `Placed` | `place()`: the quote accepted, at its prices |
| `Quoted` | `Cart` | `reopen()`: the quote declined |
| `Quoted` | `Cancelled` | `cancel()` |
| `Placed` | `Cart` | `reopen()`: the order amended; whether it still may — no receipt, nothing shipped — is the application's |
| `Placed` | `Cancelled` | `cancel()`; refunding what was paid is the payment's affair |

- **Only a cart changes**, but for the draws a placed order records after payment
  ([options.md](options.md#draws-after-payment)). Adding, changing or removing a line, changing the tax zone, the
  channel, the customer group, the delivery method or the codes, or replacing adjustments on a quoted, placed or
  cancelled order throws `OrderLocked`, and so does a move the table does not list.
- **What the buyer agrees to is what the processors give when the order is placed**: `OrderModifier::place()`
  recomputes the cart before placing it.
- **The `phil_commerce_order` workflow describes these moves**, journals them, and lets someone allowed to force a
  move outside them; `Order::expire()` cancels a placed order nobody paid in time ([lifecycle.md](lifecycle.md),
  [expiry.md](expiry.md)).

## Beside the lines

| Member | Holds | See |
|---|---|---|
| `customer()`, `assignCustomer()` | The buyer's stable identifier, never an email address; `null` for a guest | [payments.md](payments.md#an-order-paid-in-full) |
| `giftOptions()`, `offerAsGift()` | Wrapping, recycled wrap, message, hidden prices | [gifts.md](gifts.md) |
| `wishList()`, `buyFromWishList()` | The wish list the order buys from | [gifts.md](gifts.md#wish-lists) |
| `manualDiscounts()`, `grantManualDiscount()`, `withdrawManualDiscount()` | The discounts granted by hand | [discounts.md](discounts.md) |
| `status()`, `changeStatus()` | The application's status, attached to a state | [lifecycle.md](lifecycle.md#statuses) |
| `revision()`, `recordRevision()` | 1 when placed, one more per confirmed change | [order-changes.md](order-changes.md) |
| `expiredAt()`, `expire()` | When the order expired unpaid | [expiry.md](expiry.md) |

## Quotes

`Quotes` offers a cart to a customer until a date: `issue()` recomputes it one last time and freezes it; `accept()`
places it **at the prices quoted**, without recomputing anything, and is refused once the quote has expired;
`decline()` makes it a cart again. Each quote is a `Quote` — the order's number, the customer, when it was issued,
until when it is valid, `Pending`, `Accepted` or `Declined` — in the table `phil_commerce_quote`.

```php
$quotes->issue($order, 'customer-42', $clock->now(), $clock->now()->modify('+30 days'));
$quotes->accept($order, $clock->now());
```

## Amending a placed order

`OrderAmendments::reopen()` makes a placed order a cart again, unless its receipt is issued — a credit note and a
new order replace it then. The cart is changed with the `OrderModifier` and placed anew; its payments stay. What it
then lacks is `payable()`; what was paid beyond its new total is `overpaid()`, for the application to refund.
Releasing and reserving its stock again is the logistics module's. A change prepared, confirmed and versioned, with
its difference due, goes through `OrderChanges` ([order-changes.md](order-changes.md)).

## Storage

With DoctrineBundle registered, the bundle maps `Order` to the table `phil_commerce_order` and aliases
`OrderRepository` to `DoctrineOrderRepository`. **An order is one row**: its lines and adjustments are values of
the order, stored in two JSON columns of their own types, `phil_commerce_order_lines` and
`phil_commerce_adjustments`, never as entities of their own.

| Column | Type | Holds |
|---|---|---|
| `number` | `VARCHAR(64)`, primary key | The order number |
| `currency` | `CHAR(3)` | The ISO 4217 code of the order's currency |
| `lines` | JSON (`phil_commerce_order_lines`) | Each line: reference, description, quantity, unit price in minor units, currency, tax category, and a configured line's versioned configuration ([options.md](options.md#storage)) |
| `adjustments` | JSON (`phil_commerce_adjustments`) | Each adjustment: type, label, amount in minor units, currency, line with a configured line's fingerprint, neutral, origin (a row without it has none) |
| `tax_zone` | `VARCHAR(64)`, nullable | The zone the tax rates are read for |
| `tax_included` | `BOOLEAN`, default false | Whether the unit prices include the taxes |
| `channel`, `customer_group` | `VARCHAR(64)`, nullable | Who the order is sold through and to |
| `delivery_method` | `VARCHAR(64)`, nullable | The delivery method chosen |
| `codes` | JSON | The promotion codes entered |
| `state` | `VARCHAR(16)`, indexed with `placed_at` | `cart`, `quoted`, `placed` or `cancelled` |
| `placed_at`, `cancelled_at` | `DATETIME`, nullable | When the order moved |
| `customer` | `VARCHAR(255)`, nullable | The buyer's stable identifier |
| `gift` | JSON, nullable | The gift options |
| `wish_list` | `CHAR(36)`, nullable | The wish list the order buys from |
| `manual_discounts` | JSON | The discounts granted by hand |
| `status` | `VARCHAR(64)`, nullable | The application's status |
| `revision` | `INTEGER`, default 1 | One more per confirmed change |
| `expired_at` | `DATETIME`, nullable | When the order expired unpaid |
| `payments_started` | `INTEGER`, default 0 | How many payments were started; each start moves the version on ([payments.md](payments.md#paying-an-order)) |
| `version` | `INTEGER` | Moved on by each write; a write over a stale copy fails with Doctrine's `OptimisticLockException` |

- **The application creates the table with its migrations**: `doctrine:migrations:diff` sees the mapping like
  its own entities'.
- **`add()` only schedules**: the application commits its unit of work, and a change to an order it found is
  written with that same commit.
- **A stored value no code could have written** — a line with no quantity, an unknown currency — is refused on
  reading as a DBAL conversion error rather than read into an order.

## Value objects

| Type | Rules |
|---|---|
| `SellableReference::of(string $type, string $id, ?string $configuration)` | Type: up to 32 lowercase letters, digits or `_`, starting with a letter; identifier: 1 to 128 visible ASCII characters; configuration: a fingerprint of 16 lowercase hexadecimal digits, or none; `value()` is `type:id`, or `type.fingerprint:id`, read back by `fromValue()` |
| `Sellable::of(SellableReference, string $description, Money $unitPrice, string $taxCategory, ?OptionSet $options)` | A reference without a configuration, a description, a unit price of zero or more, a tax category; no options by default, `withOptions()` |
| `PricingContext::of(int $quantity, DateTimeImmutable $at, ?string $customerGroup, ?string $channel)` | At least one unit; a group or channel, when given, has a name |
| `Adjustment::of(AdjustmentType, string $label, Money $amount, ?SellableReference $line, bool $neutral, ?string $origin)` | A label the receipt shows; a signed amount; an origin of 1 to 255 visible ASCII characters, or none |

Every refusal throws `InvalidValue` from `phil/ddd-bundle`. `LineNotInOrder` and `UnknownSellable` implement its
`DomainError`.

## Elsewhere

Payments are in [payments.md](payments.md), receipts and credit notes in [receipts.md](receipts.md).
