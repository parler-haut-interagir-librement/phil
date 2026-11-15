# Expiry of unpaid orders

A placed order nobody pays expires, and an expired order is deleted once kept long enough, so that a store keeps no
more than it needs of orders that never became sales.

| Delay | Default | Where it is set |
|---|---|---|
| Hours before an unpaid placed order expires | 24 | `Channel::expireUnpaidOrders()`, per channel; `phil_commerce.order_expiry.unpaid_hours` for orders of no channel the store knows |
| Days before an expired order is deleted | 30 | `Channel::expireUnpaidOrders()`, per channel; `phil_commerce.order_expiry.delete_after_days` likewise |

`null` turns either delay off for a channel. The defaults are on, so a store minimizes what it keeps without doing
anything.

```php
// $expiry: Phil\CommerceBundle\Application\OrderExpiry, from the container, run on a schedule
$sweep = $expiry->sweep($now);
$entityManager->flush();

$sweep->expired; // the order numbers expired
$sweep->deleted; // the order numbers deleted
```

- **Unpaid** means no payment that ever held money: an order whose payments all failed, were cancelled or were
  abandoned is unpaid; one with a payment in flight, authorized, captured, refunded or disputed is not.
- **A deferred sale never expires**: its balance has its own due date ([deferred-sales.md](deferred-sales.md)).
- **Expiring cancels the order** through the `expire` transition of the workflow, and `Order::expiredAt()` remembers
  it; `OrderCancelled` announces it, with `expired` true ([lifecycle.md](lifecycle.md#announcing-a-cancellation)).
- **Deleting checks again, in one transaction**, that no payment ever held money for the order and that it has no
  receipt, then deletes the order with what belongs to it alone: its payments that captured nothing, its payment
  links, its changes and its journal.
- **A sweep is bounded**: at most `$limit` orders expired and as many deleted per channel, 1,000 by default and
  10,000 at most; the application runs it again until nothing is left.

The orders are read on the index of the state and the moment placed, through `ExpiringOrders`, which
`DoctrineOrderRepository` implements.
