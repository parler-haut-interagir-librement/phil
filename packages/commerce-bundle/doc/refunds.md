# Refunds in two steps

A refund is granted, then executed, each by someone the application allows: two rights, so that the person who
decides is not the only one to move money. A small store where one person holds both merges them.

```php
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Domain\Model\Refund;

// $refunds: Refunds, from the container
// $entityManager: Doctrine\ORM\EntityManagerInterface
$refund = $entityManager->wrapInTransaction(
    fn (): Refund => $refunds->grant($payment, $amount, 'Broken die', 'clerk-3', $now),
);
$refunds->execute($refund, 'manager-2', $now);   // asks the processor, or records an offline refund
$refunds->withdraw($refund, $now);               // or gives it up before it is executed
```

- **What is granted never exceeds what is left**: the payment's amount, less the refunds granted and not executed yet,
  and less the larger of what the processor confirmed and what was asked of it; the payment is completed. A refund
  executed but not confirmed yet thus stays owed, and the refunds of a payment never add up to more than it.
- **Two refunds granted at once wait for each other**: `grant()` locks the payment first, through the port
  `PaymentLock` (see [Two clerks at once](#two-clerks-at-once)).
- **Executing goes through `Payments::refund()`**: the processor is asked, with the refund's identifier as the
  idempotency key, so that a refund asked again — after a timeout or a crash — is made once; the refund is recorded
  when the notification comes; an offline payment's refund is recorded at once
  ([payments.md](payments.md#offline-payments)).
- **Who granted and who executed are kept**, with their moments, on the `Refund`, whose states are `granted`,
  `executed` and `withdrawn`.
- **`phil_commerce.refunds.single_step: true`** makes granting execute at once, by the same person.
- **The two rights are the application's**: it checks them with its security voters before calling `grant()` and
  `execute()`.

## Two clerks at once

Two clerks who grant a refund of the same payment at the same moment would each count what is left before the other's
refund is stored, and could together grant more than the payment. `grant()` therefore takes the payment's lock before
it counts: with DoctrineBundle, `DoctrinePaymentLock` reads the payment's row with `SELECT … FOR UPDATE`, on MySQL,
MariaDB and PostgreSQL alike, and holds it until the transaction ends. The second clerk waits until the first one's
transaction is committed or rolled back, then counts the first one's refund.

- **`grant()` runs inside a transaction**, which the application commits with the refund, as above: outside one, the
  lock would end with its statement, and `grant()` throws a `LogicException` before it counts anything. With
  `single_step`, the processor is asked inside that transaction, while the payment is locked.
- **The refunds are read under the lock** by a locking read, which sees what the other transaction committed, even
  under the repeatable reads of MySQL, where a plain `SELECT` keeps the snapshot of the transaction's first read.
- **A payment changed since it was read** — a processor's notification applied by another request in the meantime —
  throws Doctrine's `OptimisticLockException`, from the payment's version: the application reads the payment again
  and grants on the fresh copy.
- **The payment and its refunds are the ones the store holds**: what is left is never counted from a copy in memory
  older than the lock.

### Upgrading from 0.3

`grant()` called outside a transaction throws a `LogicException` from 0.4 on: wrap each call in
`EntityManagerInterface::wrapInTransaction()`, or between `beginTransaction()` and `commit()`, with the flush of the
refund. A caller that catches `PaymentRefused` to show the clerk what is left catches `OptimisticLockException` too,
and reads the payment again.

An application that stores payments elsewhere aliases its own `PaymentLock`
([extension-points.md](extension-points.md#paymentlock)); without one, `Refunds` counts the refunds
`RefundRepository::refundsOf()` gives, with no lock, which suits a single process alone, as in tests.

## The refund announced

Once a refund is executed, `Refunds` dispatches `OrderRefunded`, by its class: the order's number, the refund's and the
payment's identifiers, the order's channel and its customer's stable identifier, the amount refunded and the moment.
An activity feed or a loyalty programme withdraws what the order earned without the commerce knowing it
([extension-points.md](extension-points.md#events)). A refund granted or withdrawn is not announced; an order
`Refunds` cannot read leaves the channel and the customer `null`. With no event dispatcher, nothing is announced.

## Storage

Refunds are stored in `phil_commerce_refund` through `RefundRepository` (`DoctrineRefundRepository` with
DoctrineBundle). `Payments::refund()` stays available for a flow that needs no grant.
