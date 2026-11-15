# Refunds in two steps

A refund is granted, then executed, each by someone the application allows: two rights, so that the person who
decides is not the only one to move money. A small store where one person holds both merges them.

```php
use Phil\CommerceBundle\Application\Refunds;

// $refunds: Refunds, from the container
$refund = $refunds->grant($payment, $amount, 'Broken die', 'clerk-3', $now);
$refunds->execute($refund, 'manager-2', $now);   // asks the processor, or records an offline refund
$refunds->withdraw($refund, $now);               // or gives it up before it is executed
```

- **What is granted never exceeds what is left**: the payment's refundable amount, less the refunds granted and not
  executed yet; the payment is completed.
- **Executing goes through `Payments::refund()`**: the processor is asked, and the refund is recorded when its
  notification comes; an offline payment's refund is recorded at once ([payments.md](payments.md#offline-payments)).
- **Who granted and who executed are kept**, with their moments, on the `Refund`, whose states are `granted`,
  `executed` and `withdrawn`.
- **`phil_commerce.refunds.single_step: true`** makes granting execute at once, by the same person.
- **The two rights are the application's**: it checks them with its security voters before calling `grant()` and
  `execute()`.

## The refund announced

Once a refund is executed, `Refunds` dispatches `OrderRefunded`, by its class: the order's number, the refund's and the
payment's identifiers, the order's channel and its customer's stable identifier, the amount refunded and the moment.
An activity feed or a loyalty programme withdraws what the order earned without the commerce knowing it
([extension-points.md](extension-points.md#events)). A refund granted or withdrawn is not announced; an order
`Refunds` cannot read leaves the channel and the customer `null`. With no event dispatcher, nothing is announced.

## Storage

Refunds are stored in `phil_commerce_refund` through `RefundRepository` (`DoctrineRefundRepository` with
DoctrineBundle). `Payments::refund()` stays available for a flow that needs no grant.
