# Payouts

The bundle **moves no money**. The application pays its consignors — a transfer, a cheque, cash, store credit — and
writes here what it paid; the bundle keeps what is owed and what was paid, and the balance between them.

```php
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;

$payout = $payouts->record(
    idempotencyKey: $transfer->id,
    consignor: Owner::consignor('author-17'),
    amount: $statement->share(),
    paidAt: $transfer->sentAt,
    method: 'transfer',
    reference: $transfer->reference,
    at: $clock->now(),
);
$entityManager->flush();
```

| Field | What it holds |
|---|---|
| `idempotencyKey` | A key the application gives the payout — the identifier of the transfer, of the cheque, of the form that sent it —, 1 to 64 characters, unique. |
| `amount` | What was paid, more than zero, in `Money`: at most the balance owed in its currency. |
| `paidAt` | When it was paid, in UTC. |
| `method` | How it was paid, as the application names it: 1 to 32 lowercase letters, digits, dots, dashes or underscores — `transfer`, `cheque`, `cash`, `store_credit`. |
| `reference` | A free reference, up to 64 characters: the transfer's, the cheque's number; or `null`. |

A payout may be partial: the balance keeps the rest.

## The same payout twice

A payout whose key is written already is refused: the same payout written twice by mistake, a form sent again. The
key is unique in the table too, so that two requests that write the same payout at once cannot both flush: the second
flush throws Doctrine's `UniqueConstraintViolationException`, and nothing of it is written.

## Corrections

A payout is never changed nor deleted. A payout written by mistake is cancelled by its **reversal**:

```php
$payouts->reverse($payout->id, idempotencyKey: $payout->idempotencyKey.'-reversal', at: $clock->now());
```

The reversal is a payout of the opposite amount, by the same method and reference, that names the payout it reverses.
A payout is reversed once — the table refuses a second reversal, even from two requests at once —, and a reversal is
never reversed: the right payout is written again instead.

## The balance

```php
$balance = $payouts->balance(Owner::consignor('author-17'), Currency::of('CAD'));

$balance->owed;     // from the ledger: the shares of the units sold and bought out, less those brought back
$balance->paid;     // the payouts, reversals deducted
$balance->balance;  // owed less paid
$balance->state;    // PayoutState::Due, PartlyPaid or Paid
```

| State | When |
|---|---|
| `Due` | Something is owed and nothing was paid. |
| `PartlyPaid` | Something was paid, and something is still owed. |
| `Paid` | Nothing is owed: everything was paid, or nothing was ever owed. |

What is owed is read from the whole ledger, in one currency, as the statements are; it does not wait for a statement.
A customer's return after a payout can make the balance negative: the consignor then owes the shop, and the state is
`Paid`. The bundle does not decide how that is settled — deducted from the next payout, paid back —: the next payouts
are smaller, or the application writes what was paid back as it sees fit.

The sums count the entries and payouts this request wrote and did not flush yet: a sale and a payout in one request
see each other.
