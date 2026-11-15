# Payments

A placed order is paid through one or more payments, each through a method whose gateway speaks to one
processor. **The amount is the server's, and the state is the processor's**: a payment moves only when a
notification its gateway has verified says so, never when the buyer comes back to the store.

| Type | Role |
|---|---|
| `Domain\Model\Payment` | One payment: order, method, amount, refunded amount, state, the processor's reference |
| `Domain\Payment\PaymentState` | `New`, `Processing`, `Authorized`, `Completed`, `Failed`, `Cancelled`, `Abandoned`, `Refunded`, `Disputed`, `ChargedBack` |
| `Domain\Payment\PaymentEvent` | What a notification says: `Processing`, `Authorized`, `Captured`, `Failed`, `Cancelled`, `Refunded`, `DisputeOpened`, `DisputeWon`, `DisputeLost` |
| `Domain\ValueObject\PaymentNotification` | A verified notification: its id, the processor's reference, the event, a refund's amount |
| `Domain\ValueObject\PaymentStart` | What the buyer goes on with: the processor's reference, a page or a client secret |
| `Domain\ValueObject\OrderPayments` | An order's payments summed: captured, returned, net, payable, state |
| `Domain\Payment\OrderPaymentState` | `Unpaid`, `PartiallyPaid`, `Paid`, `PartiallyRefunded`, `Refunded` |
| `Application\Payments` | Starts payments, abandons those the buyer gave up, applies notifications, asks refunds, sums an order |
| `Application\Port\PaymentGateway` | Port: one processor, implemented by the application or a package per processor |
| `Infrastructure\Http\PaymentWebhookController` | Receives the processors' notifications |

## Paying an order

```php
use Phil\CommerceBundle\Application\Payments;

// $payments: Payments, from the container; $order: a placed order
[$payment, $start] = $payments->start($order, 'card', $clock->now());          // all that is left to pay
[$deposit] = $payments->start($order, 'card', $clock->now(), $depositAmount);  // or part of it

$orders->add($order);    // nothing is committed by Payments
$entityManager->flush(); // the application commits its unit of work

// The buyer goes on with $start->redirectUrl, or the processor's script with $start->clientSecret.
```

- **Only a placed order is paid**; a cart throws `PaymentRefused`.
- **The amount defaults to what is left to pay** (`OrderPayments::payable()`): the total less every payment that
  holds or may still hold money — new, processing, authorized, completed or disputed, less what was refunded of
  them. A failed, cancelled or abandoned payment frees its share. An amount above zero and at most that is accepted, which
  makes **split payments** a series of `start()` calls; anything else throws `PaymentRefused`, and another
  currency throws `CurrencyMismatch`.
- **The gateway of the method** hands the payment to its processor and answers with a `PaymentStart`; the
  payment keeps the processor's reference and becomes `Processing`.
- **Two payments started at once cannot both take the same share.** Each start counts on the order
  (`Order::countPaymentStart()`, column `payments_started`), which moves its version on: when two requests start a
  payment over the same copy of the order, the second commit fails with Doctrine's `OptimisticLockException` and its
  payment is not stored. The application answers it as a conflict, and the buyer starts again on the fresh order.
  The processor may have created a payment for the refused start; no notification will ever name a stored
  payment for it, and the webhook answers 404 to any that comes.

## A payment the buyer gave up

A new or processing payment holds its share of the order until its processor says it failed or was cancelled. A
buyer who leaves the processor's page often makes no notification come at all, so the application abandons it:

```php
$payments->abandon($payment);                                       // the buyer came back without paying
$payments->abandonStale($order, $clock->now()->modify('-1 hour'));  // every new or processing payment older than that
```

- **An abandoned payment frees its share**: `payable()` no longer counts it, and a new attempt may take the
  balance. `abandonStale()` returns the payments it abandoned; calling it before each new attempt, or on a schedule,
  keeps a checkout the buyer left from blocking the order for ever.
- **Only a new or processing payment is abandoned**; any other throws `PaymentRefused`.
- **A late notification still applies.** If the processor captures an abandoned payment after all, the capture is
  recorded and the payment counts again; when the order was paid otherwise meanwhile, `OrderPayments::overpaid()`
  shows the excess to refund. Choose the delay of `abandonStale()` longer than the processor's own expiry of a
  checkout, so that this stays rare.

## Notifications

`Payments::notify(string $method, string $payload, array $headers)` asks the gateway of the method to verify and
read the notification, finds the payment by the processor's reference, and applies the event.

| Outcome | When |
|---|---|
| `Applied` | The payment moved |
| `AlreadyApplied` | The notification's id was applied before: a replay changes nothing |
| `Refused` | The event does not apply to the payment as it stands; nothing changes |
| `UnknownPayment` | No payment of the method by that reference, yet or at all |

A gateway that cannot verify or read a notification throws `InvalidNotification`.

| Event | From | To |
|---|---|---|
| `Processing` | `New` | `Processing` |
| `Authorized` | `New`, `Processing`, `Abandoned` | `Authorized` |
| `Captured` | `New`, `Processing`, `Authorized`, `Abandoned` | `Completed` |
| `Failed`, `Cancelled` | `New`, `Processing`, `Authorized`, `Abandoned` | `Failed`, `Cancelled` |
| `Refunded` (with its amount) | `Completed` | `Completed`, or `Refunded` when nothing is left |
| `DisputeOpened` | `Completed` | `Disputed` |
| `DisputeWon` | `Disputed` | `Completed` |
| `DisputeLost` | `Disputed` | `ChargedBack` |

A refund of more than is left, of zero, of a negative amount or in another currency than the payment's is refused
like a move the table does not list: the outcome is `Refused`, and the webhook answers 204.

## The webhook endpoint

With DoctrineBundle, the bundle registers `PaymentWebhookController`, which the application routes with a `method`
attribute:

```yaml
# config/routes/phil_commerce.yaml
phil_commerce_payment_webhook:
    path: /commerce/webhooks/{method}
    controller: Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController
    methods: [POST]
    requirements: { method: '[a-z][a-z0-9_]{0,31}' }
```

| Answer | When | What a processor does |
|---|---|---|
| 204 | Applied, already applied, or refused by the payment's state | Stops sending it |
| 400 | The gateway does not verify or read it | Retries, then gives up |
| 404 | No gateway for the method, or no payment by the processor's reference yet | Retries |

The change is committed before the 204, so an acknowledged notification is never lost. Exclude the route from
CSRF protection and from authentication: the gateway's signature check is what admits a notification.

## Refunds

`Payments::refund(Payment $payment, Money $amount)` asks the gateway of the payment to refund part or all of
what is left of a completed payment. **The refund is recorded when the processor's notification confirms it**,
not when it is asked: the processor may refuse it, or take time. A refund granted by one person and executed by
another goes through `Refunds` ([refunds.md](refunds.md)).

## Offline payments

A method of `phil_commerce.offline_payments` — a cheque, a bank transfer, cash handed over later — is paid without a
gateway:

```php
$payment = $payments->declareOffline($order, 'cheque', $now);          // all of what is left, or part of it
$payments->confirmOffline($order, $payment, $now);                       // the money came in
$payments->cancelOffline($payment);                                      // or it never came
```

- **A declared payment holds its share** of the order as processing, named `offline:<id>`, until someone of the store
  confirms it or cancels it; `abandonStale()` never abandons it.
- **Only a declared method is paid offline**, for an amount of what is left, never more.
- **Its refunds are recorded at once** by `Payments::refund()`, since no processor will notify them.
- **Confirming announces the order** once it is paid in full, like a processor's notification.

The column `offline` of `phil_commerce_payment` tells an offline payment.

## An order paid in full

When the payment that completes an order is applied — a processor's notification, or an offline payment confirmed —,
`Payments` dispatches `OrderPurchased`, by its class, once: the order's number, its channel, its customer's stable
identifier, its total and the moment. A loyalty programme, an activity feed or a wish list listens to it without the
commerce knowing them; `WishLists` does ([gifts.md](gifts.md#wish-lists)). The event carries identifiers and the
amount only; a listener reads what else it needs from its own store, and a listener that hands it to Messenger sends
those identifiers alone.

`Payments` announces only when it can read the order (`OrderRepository`, wired by the bundle) and has an event
dispatcher.

## An order's payments

`Payments::summary(Order)` returns `OrderPayments`:

| Member | Holds |
|---|---|
| `total` | The order's total |
| `captured()` | What the processors captured, whatever happened to it since |
| `returned()` | What went back: refunds, and disputes lost |
| `net()` | Captured less returned |
| `payable()` | What a new payment may still ask, never below zero |
| `overpaid()` | What the payments that count hold beyond the total: an order amended down after it was paid |
| `state()` | `Unpaid` when nothing was captured; `PartiallyPaid` or `Paid` while nothing went back; `PartiallyRefunded` or `Refunded` once something did |

## Storage

With DoctrineBundle, a payment is one row of `phil_commerce_payment`, aliased through `PaymentRepository` to
`DoctrinePaymentRepository`.

| Column | Type | Holds |
|---|---|---|
| `id` | `CHAR(36)`, primary key | A time-ordered UUID (v7) |
| `order_number` | `VARCHAR(64)`, indexed | The order paid |
| `method` | `VARCHAR(32)` | The method, as its gateway names it |
| `amount`, `refunded` | `BIGINT` | Minor units |
| `currency` | `CHAR(3)` | The order's currency |
| `state` | `VARCHAR(16)` | The payment's state |
| `external_reference` | `VARCHAR(255)`, nullable; unique with `method` | The processor's reference |
| `applied_notifications` | JSON | The ids of the notifications applied |
| `offline` | `BOOLEAN`, `false` by default | Whether the payment is paid offline |
| `created_at` | `DATETIME` | When the payment was created |
| `version` | `INTEGER` | Moved on by each write; two notifications applied at once cannot both win |

## The payment attempt limiter

`Payments::start()` does not call the limiter: the application decides the attempt with `PaymentAttemptLimiter`
before starting a payment, and records it after ([payment-attempts.md](payment-attempts.md)).
