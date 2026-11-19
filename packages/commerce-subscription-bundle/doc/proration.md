# Changing tier at once

`Subscriptions::changeTo()` changes tier at the next renewal. `Subscriptions::changeNow()` changes it now: the
subscriber pays the difference for the time left, on an order of its own, and the change takes effect when that order
is paid.

```php
$order = $subscriptions->changeNow($subscription, 'premium', $orderNumbers->next(), $taxZoneOf($subscription->subscriber), $clock->now());

if (null !== $order) {
    $orders->add($order);                                  // the core's OrderRepository
    [$payment, $start] = $payments->start($order, 'card', $clock->now());
}

// When the core applied the payment's notification
$subscriptions->paid($subscription, $order->number, $clock->now());          // the change takes effect, or
$subscriptions->paymentFailed($subscription, $order->number, $clock->now()); // the change is given up
```

## What is charged

The order has one line `subscription:<id>`, which sells a whole period of the new tier at its price from the
resolvers, never a price from a form. `ProrationOrderProcessor`, an `OrderProcessor` of the core, then puts
`Proration` adjustments on that line:

| Adjustment | Origin | Amount |
|---|---|---|
| Time already used | `proration:time` | Within the same period: the share of the new tier's price for the time already gone, taken off |
| Credit for the unused period | `proration:credit` | The share of the current period's price for the time left, taken off |
| Credit carried over | `proration:carried` | What an earlier change left over, taken off |

- **Within the same period** — monthly to monthly — the new tier is charged for the time left, and the period keeps
  its end.
- **To a tier of another period** — monthly to yearly — the new tier is charged a whole period, which starts at the
  change; only the unused part of the current period is credited.
- **The current period's price is what was ordered for it**: the line's subtotal with its options and after its
  promotions, before taxes. A tier repriced since does not change the credit, and a second change in the same period
  credits the time left at the price of the tier changed to first.
- **The core taxes the line after its proration**, with the rates of its tax category in the order's zone: the tax
  is on what the subscriber pays.
- **The credits never bring the line below zero.** What the order does not use is carried over, once it is paid, and
  taken off the next orders of the subscription — renewals or changes — until spent. A renewal whose payment fails
  keeps the credit for the next try.
- Every amount is a `Money` in the order's currency, which is the subscription's: a tier priced in another currency is
  refused.

## Rounding

Shares are counted in seconds, from the start of the current period to its end, and computed with
`Money::allocate()`: **the share of the time left is rounded to the nearest minor unit, a tie going to the time
left**. Nothing is lost or created by rounding: the share of the time left and the share of the time used always add
up to the price. A currency without decimals, the yen, is rounded to the yen: 23 days left of 30 credit 767 of a
1,000 yen period.

## Which changes are prorated

| Subscription | What `changeNow()` does | Returns |
|---|---|---|
| Active, the period running | Orders the change, prorated | The order |
| Active, the day of the renewal, its period over | Renews at the new tier, at its whole price, as `order()` does | The order |
| Pending, not ordered yet | Orders its first period at the new tier | The order |
| Trialing | Switches tier at once, without an order; the trial keeps its end and grants the new tier's features | `null` |
| Asked for a lower tier | Waits for the next renewal, as `changeTo()` does, unless `immediate_downgrades` is set | `null` |
| Asked for its current tier | Takes a change waiting for the renewal back | `null` |
| Past due, cancelled, or awaiting an order | Refused: the overdue period is settled, or the cancellation taken back, first | — |

A tier is lower when its rank is lower. With `immediate_downgrades`, a change to a lower tier is ordered and prorated
the same way: its credit is usually more than the new tier costs, so the order is zero and the rest is carried over.
An order whose total is zero has nothing to charge: the application confirms it with `paid()` at once.

## While the change is awaited

- The subscription keeps its tier and its features until the order is paid. `pendingChange()` returns the
  `ImmediateChange`: the tier changed to, the moment, the seconds left and the credit.
- Its renewal is not ordered, and no other change is taken, while the order is awaited.
- A failed payment gives the change up: the subscription stays as it was, never past due, and its credit carried over
  is kept.
- An order that cannot be placed gives the change up before anything is awaited.

## Storage

The columns the change adds to `phil_commerce_subscription`:

| Column | Type | Holds |
|---|---|---|
| `currency` | `CHAR(3)` | The currency of every amount of the subscription |
| `period_amount` | `BIGINT` | The price of a whole period of the current tier, as last ordered, in minor units |
| `carried_credit` | `BIGINT` | The credit carried over |
| `awaited_amount`, `awaited_credit` | `BIGINT` | The price of a whole period on the awaited order, and the credit it takes off |
| `change_offer`, `change_at` | `VARCHAR(64)`, `DATETIME`, nullable | The tier changed to at once, and when |
| `change_seconds_left`, `change_period_seconds` | `INTEGER` | The time left, and the length of the period; `null` when the change starts a period |
| `change_credit` | `BIGINT` | The credit of the change |
