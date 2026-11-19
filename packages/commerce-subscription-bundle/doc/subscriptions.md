# Subscriptions

A subscription holds one subscriber to one offer. **Each period is paid by an order of the core**: the module places
the order, the application stores it and takes its payment through the core, then tells the subscription how the
payment went.

## States

| State | Access | Reached by |
|---|---|---|
| `Pending` | No | Subscribing to a paid offer without trial |
| `Trialing` | Yes | Subscribing to an offer with a trial |
| `Active` | Yes | Subscribing to a free offer; a period paid |
| `PastDue` | As its offer's dunning stages say: every feature by default ([dunning.md](dunning.md)) | A renewal's payment failed; it is being retried |
| `Ended` | No | Cancelled and its period over; the last retry failed; cancelled while pending or past due; pending longer than `abandon_pending_after_days` |

## The loop the application keeps

```php
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;

// When the subscriber subscribes
$subscription = $subscriptions->subscribe($accountId, 'pro', $clock->now());

// Once in a while, from a scheduled command
$refused = static fn (Subscription $subscription, SubscriptionRefused $refusal) => $logger->error($refusal->getMessage(), ['subscription' => $subscription->id]);

foreach ($subscriptions->sweep($clock->now(), $refused) as $due) {
    $order = $subscriptions->order($due, $orderNumbers->next(), $taxZoneOf($due->subscriber), $clock->now());
    $orders->add($order);                                  // the core's OrderRepository
    [$payment, $start] = $payments->start($order, 'card', $clock->now());
}
$entityManager->flush();

// When the core applied the payment's notification
$subscriptions->paid($subscription, $order->number, $clock->now());          // or
$subscriptions->paymentFailed($subscription, $order->number, $clock->now());
```

- **`sweep(at, refused, limit)`** ends the pending subscriptions older than
  `phil_commerce_subscription.abandon_pending_after_days` (7 by default) — signed up and never paid —, ends those
  cancelled at the end of a period now over, renews the free ones without an order — a trial whose subscriber
  changed to a free tier included —, and returns those whose period must be ordered: pending ones that await no
  order yet, and trials or periods over.
  - Each kind is read by a query of its own; a pending subscription that already awaits its first payment is not
    read at all until it is paid, fails for good or is abandoned.
  - **One subscription the sweep cannot handle never stops the others**: its `SubscriptionRefused` — its offer
    gone, a state it refuses — is handed to the `refused` callable, when given, and the sweep goes on.
  - `limit` caps each of the three reads, for a sweep run in batches; what is left is read by the next run.
- **`order(subscription, number, taxZone, at)`** places an order of the core with one line `subscription:<id>` —
  the next period of that subscription at its renewal tier, the tier changed to if any — at the tier's current price,
  taxed by the core in the zone given, and records it as awaited. The line sells the tier even once it is withdrawn
  from sale, and only while the subscription awaits no order. A subscription that is not due is refused before
  anything is placed.
- **`paid(subscription, orderNumber, at)`** starts the first period, or adds the next one; a change of tier takes
  effect. A payment of another order than the awaited one is refused.
- **`paymentFailed(subscription, orderNumber, at)`** keeps a pending subscription waiting for another try; a trial
  or a renewal becomes past due, and ends after `phil_commerce_subscription.payment_attempts` failures (3 by
  default), or at once when the subscriber cancelled while the payment was in flight. Each retry is a new payment
  of the same order, through the core.
- Nothing is committed by `Subscriptions`: the application commits its unit of work.

## Periods

- **A trial paid early** starts its first period when the trial ends; paid later, when the payment comes.
- **A renewal paid a few days late** does not move the next end: periods are counted from the anchor
  ([offers.md](offers.md#periods)).
- **A tier of another period** — monthly to yearly — starts where the last period paid ends.

## Cancelling and changing tier

| Call | Effect |
|---|---|
| `cancel(subscription, at)` | Active or trialing: ends at the end of the paid period, and is no longer due. Pending or past due: ends now. Ended: refused |
| `resume(subscription)` | Takes a cancellation back before the period ends |
| `changeTo(subscription, offerCode)` | The tier the next period is ordered at, within the family; the current tier takes a change back. Refused for another family, another currency, a withdrawn tier, an ended subscription or one awaiting a payment |
| `changeNow(subscription, offerCode, number, taxZone, at)` | The tier changed now, on an order of its own, the unused part of the period credited — see [Changing tier at once](proration.md) |

**Cancelling while the next period's order awaits its payment** leaves the outcome to that payment, so that no
money is captured for nothing: paid, the period is granted — the subscriber keeps what they paid for — and the
subscription ends at its end; failed, it ends at once. The sweep does not end a cancelled subscription while an
order is awaited. An application that wants no charge at all cancels the order's payment through the core
(`Payments::abandon()`, or the processor), then calls `paymentFailed()`.

## Storage

With DoctrineBundle, a subscription is one row of `phil_commerce_subscription`.

| Column | Type | Holds |
|---|---|---|
| `id` | `CHAR(36)`, primary key | A time-ordered UUID (v7) |
| `subscriber` | `VARCHAR(255)`, indexed | The application's id of the subscriber |
| `payer` | `VARCHAR(255)`, nullable | Who pays the renewals when it is not the subscriber: the buyer of a gift |
| `family`, `offer`, `next_offer` | `VARCHAR(64)` | The family, the current tier, the tier changed to |
| `state` | `VARCHAR(16)`, indexed with `period_end` | The state |
| `anchor`, `period_end` | `DATETIME`, nullable | The anchor of the periods, the end of the trial or of the last period paid |
| `periods_paid`, `periods_since_anchor` | `INTEGER` | Periods paid in all, and since the anchor |
| `period_unit`, `period_count` | `VARCHAR(8)`, `SMALLINT` | The period last paid |
| `cancel_at_period_end` | `BOOLEAN` | Cancelled, ending at the period's end |
| `awaited_order` | `VARCHAR(64)`, nullable | The order of the core whose payment is awaited |
| `failed_payments` | `SMALLINT` | Failures since the last payment |
| `past_due_since` | `DATETIME`, nullable | The first failure of the renewal past due, which its offer's dunning stages count from |
| `created_at`, `ended_at` | `DATETIME` | When it started, and ended |
| `version` | `INTEGER` | Moved on by each write |

The columns of an immediate change and of the credit are described in [Changing tier at once](proration.md#storage).
