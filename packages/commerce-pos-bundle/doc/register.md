# The register

## Registers

A register is a till: its code, its name, the currency its drawer holds, the increment cash rounds to, its role —
the main register of its location, or a seller's mobile one ([devices.md](devices.md)) —, and, if the application
uses them, the stock location it sells from and the channel its sales belong to.

```php
$registers->create('front', 'Front counter', Currency::of('CAD'), cashIncrement: 5, location: 'store-1', channel: 'shop', role: RegisterRole::Main);
```

## Sessions

A session is a shift. It opens on the float counted in the drawer, and the drawer's expected cash follows every
movement until the close.

```php
$session = $cashSessions->open('front', 'cashier-7', Money::of(20_000, $cad), $clock->now());
$cashSessions->paidIn($session->id, Money::of(5_000, $cad), 'cashier-7', $clock->now(), 'Change from the safe');
$cashSessions->paidOut($session->id, Money::of(1_250, $cad), 'cashier-7', $clock->now(), 'Milk');
$cashSessions->drop($session->id, Money::of(30_000, $cad), 'cashier-7', $clock->now());
$report = $cashSessions->close($session->id, Money::of(41_870, $cad), 'manager-2', $clock->now());
```

One session is open per register at a time. People are named by whatever the application identifies them with.

### A day at prices that include the taxes

At a fair, the price on the tag is the price paid. A session opened with `taxIncluded: true` sells every cart that
way: a 10.00 book is rung up at 10.00, with GST 0.44 and QST 0.87 taken out of it, and the receipt says so after the
total.

```php
$session = $cashSessions->open('fair', 'cashier-7', Money::of(20_000, $cad), $clock->now(), taxIncluded: true);
$checkout->start($order, $session);  // before the first line: the cart is priced as the session sells
```

`start()` sets the cart to the session's way and recomputes it. Ringing up a cart priced otherwise than its session
throws `PosRefused`, since the customer would have seen one total and paid another. A sale imported from offline
takes its session's way.

## The cart

The cart is the core's order, built with its `OrderModifier`. The register adds what only a till does:

```php
$checkout->start($order, $session);                                            // price it as the session sells
$checkout->hold($order, 'front', 'Mme Tremblay', 'cashier-7', $clock->now()); // park it
$order = $checkout->resume('A-0042', 'front');                                 // pick it up again
$checkout->tip($order, Money::of(300, $cad));                                  // or tipShare($order, 150_000) for 15 %
$checkout->ring($order, $session, 'cashier-7', $clock->now());                 // place it as a sale of the session
```

A tip is an adjustment of the core's type `Tip`: on no line, so no tax applies; a share is taken of the items before
taxes, the taxes the prices include taken out. Ringing up recomputes the order one last time, the tip kept.

## Payments

Each payment is a payment of the core, and several make a split payment. Each is its own transaction: the core reads
what is left to pay from the payments already stored.

```php
$tender = $registerPayments->cash($order, $session, tendered: Money::of(5_000, $cad), by: 'cashier-7', at: $clock->now());
$tender->due;     // 22.00 for 21.98 owed, rounded
$tender->change;  // 28.00

$registerPayments->recorded($order, $session, 'debit', Money::of(1_000, $cad), approval: 'A1B2C3', at: $clock->now());
$registerPayments->count($giftCardPayment, $session, $clock->now());   // a payment another module took
$terminalPayments->charge($order, $session, 't1', $clock->now());       // a connected terminal: terminals.md
```

**Rounding is the drawer's**: the payment records the exact amount owed, 21.98, and the drawer the cash taken, 22.00;
the difference is kept on the movement and totalled in the report. The order and its taxes are never changed.

Refunds: `refundCash()` gives cash back out of the session's drawer, rounded the same way; `refundRecorded()`
records a refund made on the terminal. Both take an idempotency key, the same on every try of one refund: asked again
under its key — a second tap, a retry —, a refund is recorded once, and a cash refund gives nothing more and moves no
cash.

## The end-of-day report

`SessionReport`, from `close()` or `report()` while the session is open:

| Part | Content |
|---|---|
| The drawer | Float, cash sales, cash refunds, paid in, paid out, drops, rounding |
| The count | Expected, counted, over or short |
| `byMethod` | The payments taken, by method |
| `tips`, `sales` | The tips of the sales rung up, and how many there were |

The report reads no order: each sale keeps the tip its order took when it was rung up, in the `tip` and `currency`
columns of `phil_commerce_pos_sale`, so a busy day costs one query of the sales, not one per sale.

Over several sessions, the cash-difference report lists what each count found over or short, and flags the large
ones ([counter.md](counter.md#the-cash-difference-report)).
