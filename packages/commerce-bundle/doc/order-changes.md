# Changes of a placed order

A placed order changes through a change that is prepared, then confirmed or abandoned, and each confirmed change
counts one more revision of the order. The difference it makes to the total is known before and after.

```php
use Phil\CommerceBundle\Application\OrderChanges;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;

// $changes: OrderChanges, from the container
$change = $changes->prepare($order, [
    OrderChangeLine::of(SellableReference::of('product', 'dice'), 3), // three dice instead of one
    OrderChangeLine::of(SellableReference::of('product', 'mat'), 0),  // no mat any more
], 'Customer called', 'employee-7', $context, $now);

$change->difference(); // what the preview adds to the total, negative when it lowers it

$changes->confirm($change, $order, 'employee-7', $context, $now);
$order->revision();                                  // 2
$payments->summary($order)->payable();               // what the order now lacks
$payments->summary($order)->overpaid();              // what it holds beyond its total, to refund
```

- **Preparing touches nothing**: the preview is computed on a copy of the order, reopened and changed through
  `OrderModifier` at the prices of the moment; the change keeps the total before and the total the preview gives.
- **Confirming applies the change**: the order is reopened, its lines set, recomputed and placed again — through the
  workflow when it is wired ([lifecycle.md](lifecycle.md)) —, then `Order::recordRevision()` counts its new revision,
  which the change keeps with the total the order ended with.
- **A change is confirmed on the revision it was prepared on**: once another change is confirmed, an older one is
  refused as stale (`OrderChangeRefused::stale()`), and prepared again.
- **Only a placed order without a receipt changes**: once its receipt is issued, a credit note and a new order replace
  it ([receipts.md](receipts.md)).
- **A line sets the quantity the order ends with**: 0 removes it, a reference not in the order adds it, without
  options; a line with options is changed in the cart, through `OrderAmendments`.
- **The payments stay**: what the order lacks is paid like any balance, what it holds beyond its total is refunded
  ([refunds.md](refunds.md)).

`OrderChange` states are `prepared`, `confirmed` and `abandoned`; changes are stored in `phil_commerce_order_change`
through `OrderChangeRepository` (`DoctrineOrderChangeRepository` with DoctrineBundle).
