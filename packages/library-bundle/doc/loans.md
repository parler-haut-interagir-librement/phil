# Loans

`Loans` lends the copies on `phil/reservation`: each copy is a `Bookable` of one unit, open always, and each loan that
keeps it — reserved or active — a booking of it. A loan asked for over a span another one keeps is refused, and so is a
loan longer than the policy allows or a reservation further ahead than it allows.

## Lending

```php
$loan = $loans->lend('G-0042', 'card-0007');                 // due at the end of its last day
$loan = $loans->lend('G-0042', 'card-0007', $dueAt);         // due when given
```

A loan starts now. Its due date is `loans.days` days later, at 23:59:59 on the library's clock, unless one is given.

## Reserving and checking out

```php
$reserved = $loans->reserve($copyId, 'card-0007', $start, $due);
$loans->checkOut($reserved->id);    // at the desk: active from now, its due date kept
$loans->cancel($reserved->id);
```

A reservation is for a span to come, starting at most `loans.reservation_days` days ahead. It is checked out at the
desk — from now if it is early, keeping its start if it is late — once its copy is back, and before its due date; a
reservation past its due date is cancelled and the copy lent instead.

## Renewing

```php
$loans->renew($loan->id);
```

A renewal pushes the due date back by a loan's length, counted from the due date, `loans.max_renewals` times at most.
It is refused when another loan reserved the copy after it. Its reminders and notices start over.

## Returning and losing

```php
$loans->giveBack('G-0042');                                          // the condition kept
$loans->giveBack('G-0042', CopyCondition::Incomplete, 'Two tiles missing.');
$loans->lose($loan->id, 'Never came back.');                          // the copy withdrawn
```

The copy that is out comes back by its barcode, with the condition it is in and a note when they changed. A copy lost
closes its loan and is withdrawn.

## The member

A member is named by the application's stable identifier: 1 to 128 letters, digits, dots, underscores, colons and
hyphens. An e-mail address is refused, at the desk's form and in the domain: an address changes and identifies a
person, and the library keeps the identifier instead.

A member holds at most `loans.max_open` loans, reserved or out. With `loans.block_when_overdue`, a member with a copy
past its due date borrows nothing more until it comes back.

## Refusals

A loan, a reservation, a check-out or a renewal that cannot be made throws `LoanRefused`, whose `reason` is a stable
code and `parameters` the numbers of its message — translated in the `phil_library` domain as `refusal.<reason>`:

| Reason | When | Parameters |
|---|---|---|
| `withdrawn` | The copy is withdrawn | |
| `taken` | Another loan keeps the copy over the span | |
| `too_far_ahead` | The reservation starts further ahead than allowed | `minutes` |
| `too_long` | The loan lasts longer than allowed | `minutes` |
| `overdue` | The member has a copy overdue | |
| `limit` | The member holds as many loans as allowed | `max` |
| `renewals` | The loan was renewed as many times as allowed | `max` |

A copy, a loan or a title that does not exist, or a copy that is not out, throws `NotFound`; a value out of its bounds
or a change the loan's state forbids throws `InvalidLibraryData`.

## Two loans at once

Each loan claims its copy: the claim raises the copy's version, written in the same flush as the loan. Two loans of
one copy made at once from the same read cannot both be kept: the second flush throws Doctrine's
`OptimisticLockException` and writes nothing.

## The events

`CopyLent` — a copy lent or a reservation checked out — and `CopyReturned` — with the condition the copy came back in
— are dispatched through the PSR-14 dispatcher when the application has one. They carry identifiers only.

## The stockroom link

A copy can be an item of `phil/stockroom`: `Copy::stockroomItem()` names it. With `phil/stockroom-bundle` installed,
or the option `stockroom` set to the application's `Stockroom` service, its loans move the item:

| Loan | Stock movement |
|---|---|
| Lent, or checked out | `Out`, from the item's containers to the holder `loan`'s identifier |
| Returned | `Return`, from that holder to the item's home |
| Lost | `Loss`, from that holder |

Each movement refers to the loan; the member is never named in the stockroom's journal. The stock is moved before the
loan is kept, so a stockroom that refuses — the item retired, not in stock — stops the loan. Without a stockroom, a
linked copy moves nothing.

### With phil/stockroom-bundle

When `phil/stockroom-bundle` is installed and `stockroom` names no other service, the link uses the bundle's
`Stockroom`, on its tables, and each movement runs in one of the bundle's transactions, the row of its item locked, so
that two loans of copies of one item never write over each other's balances.

| Where the stockroom's tables are | What a loan does |
|---|---|
| On the library's connection, `doctrine.dbal.default_connection` — the bundle's default | Lending, checking out, returning and losing each run in one transaction of that connection: the movement, the loan and the copy are kept together or not at all. |
| On another connection | The movement is committed in its own transaction first, then the loan is kept, as without the bundle: a loan that fails afterwards leaves the movement written, and the desk gives the item back by a return. |
| In memory (`store: memory`) | The movement is made in memory, then the loan is kept. |

Reserving, renewing and cancelling move no stock and run no such transaction.

### Without the bundle

With `stockroom` naming a `Stockroom` of the application, the movement and the loan are two writes, the stock first.
An application whose stores share the library's database wraps the calls of `Loans` in its own transaction, or
implements `Application\Contract\LoanTransaction` and aliases it: `Loans` then runs each loan that moves stock in it.
