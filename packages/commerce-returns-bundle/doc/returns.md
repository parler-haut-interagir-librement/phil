# Returns

## The way of a return

```text
requested → authorized → received → resolved
requested, authorized → awaiting information → requested
requested, awaiting information, authorized → rejected, cancelled
authorized → expired
```

```php
$return = $returns->request($order, [
    ['line' => 'product:TEA-1', 'quantity' => 2, 'reason' => ReturnReason::Defective],
    ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::ChangedMind],
], $clock->now());

$returns->authorize($return->id, $clock->now());          // or reject($id, 'Opened and used', $at), cancel($id, $at),
                                                          // askForInformation($id, 'A photo, please', $at)
$returns->receive($return->id, [
    'product:TEA-1' => ItemCondition::Resellable,
    'product:MUG' => ItemCondition::Damaged,
], $clock->now());
$settlement = $returns->refund($return->id, $clock->now());   // or credit($id, $customer, $at), exchange($id, $newOrder, $at)
```

A line is named as the order names it, `type:id`. A request is refused for an order that is not placed, past
`window_days`, for a line the order lacks, for a part of one of its sets, or for more units than are left once the
other returns of the order — rejected, cancelled and expired ones aside — are counted.

At the counter, the same steps follow one another at once.

| Step | What it does |
|---|---|
| `request($order, $lines, $at, $requester)` | Values the lines and holds their units; a requester — the customer's stable identifier — is held to the rate limiter when `rate_limiter` names one |
| `askForInformation($id, $note, $at)` | From requested or authorized: the note says what the shop needs, in 1 to 255 characters; `provideInformation($id, $at)` makes it requested again |
| `authorize()`, `reject()`, `cancel()` | A rejection says why; a return waiting for information may be rejected or cancelled |
| `expire($now)` | Expires the returns authorized more than `expiry_days` ago whose goods never came, and frees their units; a scheduled task calls it; nothing when `expiry_days` is `null` |
| `label($id, $at)` | Asks the `ReturnLabels` adapter for a carrier's label for an authorized return, and keeps its carrier, tracking number and file |
| `receive()`, then `refund()`, `credit()` or `exchange()` | See below |
| `anonymize($id)` | Erases the note — a rejection, a question —, keeping what the accounts need |

### The window

`window_days` counts from the order's placement, or, with `window_from: shipped`, from its last shipment, which
`phil/commerce-logistics-bundle` knows; an order nothing of which shipped yet counts from its placement. The last
moment of the window is included.

## What a return is worth

Each unit is worth its share of what its order line came to: the line's subtotal, its promotions and its taxes —
neutral adjustments, already in the price, aside —, divided among its units to the nearest minor unit. A line
returned in several returns is shared so that the parts add up to the line: each return is worth the share of the
units up to and including its own, less the share of the units earlier returns still hold — a line of 2.00 for 3
units, returned one unit at a time, gives 0.67, 0.66 and 0.67. Delivery, tips and anything else on no line are not
given back.

The restocking fee, `restocking_fee` parts per million, is kept on the lines whose reason is not the shop's fault —
`ChangedMind`, `Other`; never on `Defective`, `Damaged`, `WrongItem` or `NotAsDescribed`. A unit received `Missing`
is worth nothing.

### Sets

A set — a box assembled once and sold as one product, a composite product of `phil/commerce-catalog-bundle` — is one
line of the order: its units come back whole. A request naming one of its parts is refused:

```text
In the order A-1, product:DICE came in the set product:BOX: the set comes back whole, never a part of it.
```

The `SetParts` port tells which lines are sets; `CatalogSetParts` reads the catalogue when it is registered.

### Specials

A special — a price for products bought together, a bundle price of `phil/commerce-promotions-bundle` — keeps a line
per product, each with its share of the discount. A product returned alone is given back at its value less the
special's discount, which disappears: the products kept are paid in full.

| Order | Return | Given back |
|---|---|---|
| A book at 30.00 and dice at 20.00, 40.00 together | The book | 30.00 − 10.00 = 20.00; the dice cost 20.00 |
| The same | Then the dice | 20.00; 40.00 in all, what was paid |
| Two pairs, 80.00 | One book | 30.00 − 10.00: one special broken, the other kept |

- **The line's value** is its share of what the order line came to with the special's discount put back, and the
  tax that discount saved.
- **The return is charged** the part of each special's discount, taxes included, that the units back so far break:
  the share of the most returned of its lines, halves up, less what earlier holding returns were charged. It is kept
  on the return, by the adjustment's origin, `specialCharges()`.
- **The charges never take the lines below zero**: a return is never worth less than nothing on account of them.

The `SpecialDiscounts` port tells which promotion adjustments are specials; `PromotionSpecials` reads the promotions
when they are registered. Without it, every discount stays on its line, as the share described above.

### The delivery

The delivery charge — the order's shipping adjustments on no line, their waivers included, a charge already in the
prices aside — comes back once per order, with the first holding return that `delivery_refund` lets give it back:

| `delivery_refund` | Given back with |
|---|---|
| `never` | No return |
| `shops_fault`, the default | A return whose every line is the shop's fault, or the one that brings back every unit of the order left |
| `whole_order` | Only the return that brings back every unit of the order left |

It adds to the return's value, `deliveryRefund()`; a rejected, cancelled or expired return lets the next one give it
back.

## Restocking

On reception, every `Restocking` service is asked to put back the units received `Resellable`. The module holds no
stock itself. It is given the reference of the order line, read back by `SellableReference::fromValue()`: the
fingerprint of a configured line stays on it, and what goes back on the shelf is its `unconfigured()` sellable.

## Settling

| Resolution | What happens |
|---|---|
| `refund()` | The value goes back to the order's completed payments, the latest first, each up to what it can still refund — payments of method `exchange` aside; what none can take is the settlement's shortfall |
| `credit()` | The value becomes the customer's store credit, through `phil/commerce-stored-value-bundle` |
| `exchange()` | The value pays a placed order in the same currency, as a payment of the core of method `exchange`; what that order does not take is refunded |

`Settlement` tells the value, each refund and its payment, the shortfall, the credit, the exchange payment, and the
refunds that failed. Refunds go through the core's `Payments::refund()`, each under the idempotency key
`<return id>:<payment id>`, the same on every try, so that a processor asked again refunds once: a processor's refund
is recorded when its notification comes.

- **A payment of method `exchange` is never refunded as money.** Its value came from an earlier return; refunding it
  would move nothing. Its part stays in the shortfall, which the application gives back as it decides — as store
  credit, say.
- **A processor that fails is passed over, not rolled back.** Refunds sent to other processors cannot be called
  back, so the failure goes to `Settlement::$failures` — the payment, the amount and the exception — and its part
  goes to the next payments, or to the shortfall. The return is resolved with the refunds that went out, and the
  application retries the failed part itself, with `Payments::refund()` and an idempotency key of its own.

The credit note of a return, part of a line included, is `ReturnCreditNotes::issue()` — see
[Credit notes and slips](credit-notes-and-slips.md).

## Labels

`label()` asks the application's `ReturnLabels` adapter — a carrier's, which knows the shop's account and the
customer's address — for the label of an authorized return. The `ReturnLabel` it gives back — a carrier and a tracking
number in visible ASCII, and the adapter's own reference to the label's file — is kept on the return, `label()`, and
printed on its slip. Without an adapter, `label()` is refused.

## Events

With an event dispatcher, each step is told, carrying identifiers, states and amounts only; a listener — the stock,
a notification, the accounts — reads the rest from its store.

| Event | When |
|---|---|
| `ReturnStateChanged` | Each step: requested (from no state), authorized, sent back for information, requested again, rejected, cancelled, received, resolved, expired — the return, the order, the state before and after, the moment |
| `ReturnSettled` | After the resolution's `ReturnStateChanged`: the return, the order, the resolution, the value |
| `ReturnCreditNoteIssued` | A credit note issued: its number, the return, the order, its total |
