# Sales on credit

A buyer — an account, a tenant — buys on credit: on account, up to a limit, each purchase due after the terms, 30
days for "net 30".

## Accounts

```php
$creditSales->open('tenant-42', Money::of(500_000, $cad), termsDays: 30);
$account = $creditSales->account('tenant-42');
$account->change(Money::of(1_000_000, $cad), 45);   // a new limit, new terms
$account->suspend();                                // no purchase on account until resume()
```

`owed` is what the buyer owes; `available()` the limit less that, never below zero.

## Buying on account

```php
$receivable = $creditSales->charge($order, 'tenant-42', $clock->now());   // or part of it, with an amount
```

The purchase is a payment of the core of method `on_account`, completed at once, and a `Receivable` due after the
account's terms. A purchase beyond what is available is refused before anything is recorded. Like every payment of
the core, each is its own transaction.

## The buyer's payments

```php
$creditSales->receive($receivable->id, Money::of(250_000, $cad));   // a transfer, a cheque
$creditSales->overdue('tenant-42', $clock->now());
```

A payment is applied up to what is outstanding, the part taken returned, and lowers what the buyer owes. A
receivable is overdue past its date while something is outstanding.

## Refunds

The core's `Payments::refund()` reaches `AccountGateway`, which credits the receivable up to what is still outstanding
and lowers what the buyer owes; the refund is recorded on the payment at once, under its idempotency key, so that a
refund asked again under the same key credits nothing more. What the buyer already paid beyond that is owed back to the
buyer, and the receivable keeps it as `refundDue()`:

```php
$creditSales->refundsDue('tenant-42');                                  // the receivables with something owed back
$creditSales->settleRefund($receivable->id, Money::of(1_500, $cad));    // given back by transfer or cheque
```

`settleRefund()` takes up to what is due and returns the part taken. How the money goes back — a transfer, a
cheque, a credit note — is the application's choice.

## Shipping before the payment

With `phil/commerce-logistics-bundle`, an order ships once its payments cover what it is due. `CreditShipmentAuthorization`,
registered as one more authorization of the logistics, lets a buyer on credit receive an order first: the order's
customer has an active credit account, in the order's currency, whose available credit covers what is left to pay.

```php
$shipments->ship($order, $clock->now());                            // a buyer within the credit receives the order
$creditSales->charge($order, $order->customer(), $clock->now());    // owed on account when the application invoices
```

The authorization only answers: what the buyer owes changes once `charge()` puts the purchase on the account. An order
of a guest, of a buyer without an account or with a suspended one, or beyond the available credit waits for its
payments, or is forced through the logistics by someone allowed to.

## Storage

| Table | Holds |
|---|---|
| `phil_commerce_wholesale_credit_account` | `buyer` (primary key), `currency`, `credit_limit`, `terms_days`, `owed`, `active`, `version` |
| `phil_commerce_wholesale_receivable` | `id` (UUID v7), `buyer`, `order_number`, `payment`, `amount`, `currency`, `paid`, `credited`, `refund_due`, `issued_at`, `due_at`; indexed by buyer and by payment |
