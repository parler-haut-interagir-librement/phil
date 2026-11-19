# Layaways

A layaway puts goods aside for a customer on a deposit, and lets them pay in instalments until a due date.

```php
$checkout->ring($order, $session, 'cashier-7', $clock->now());
$registerPayments->recorded($order, $session, 'debit', $layaways->depositFor($order), 'A1B2', $clock->now());
$layaways->open($order, 'customer-42', dueAt: $clock->now()->modify('+60 days'), at: $clock->now());
```

The deposit is `layaway_deposit` parts per million of the order, rounded up; opening with less paid is refused.
Only a placed order is put aside, and only once: a cart, whose total may still change, a cancelled order, or an order
already put aside throws `PosRefused`.
Each instalment is a payment of the core, by any method. Once nothing is left to pay, `settle()` completes the
layaway and the goods go home; `due($at)` lists the open layaways past their date, and `cancel()` gives one up,
cancelling its order.

Holding the goods is the stock's affair — a reservation of `phil/commerce-logistics-bundle`, if the application uses
it —, and so is refunding the instalments of a cancelled layaway, by the application's own policy.
