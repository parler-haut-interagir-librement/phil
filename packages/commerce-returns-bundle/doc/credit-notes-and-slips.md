# Credit notes and slips

## The credit note of a return

```php
$note = $entityManager->wrapInTransaction(fn () => $creditNotes->issue($return->id, $clock->now()));
```

`ReturnCreditNotes::issue()` issues the credit note of a return whose goods came back — received or resolved —, for
exactly what it gives back. The core's credit notes, `Receipts::credit()`, reverse whole lines; a return's reverses
part of a line, its restocking fee, the specials it breaks and the delivery it gives back.

| Part of `ReturnCreditNote` | What it says |
|---|---|
| `number` | Taken from the core's series of credit notes, so the receipts' credit notes stay one sequence without gaps |
| `returnId`, `orderNumber`, `receiptNumber`, `issuedAt`, `currency` | What it credits, and when |
| `lines()` | Each line that came back: its reference, its description as the receipt sold it, its units and the line's, its amount — its share of what the line came to, taxes included —, the tax that amount holds, its restocking fee |
| `specialDiscount()` | The discounts of the specials the return breaks, never more than the lines give |
| `delivery()` | The delivery charge given back |
| `total()` | The lines less their fees, less the specials, plus the delivery: the return's value |

Amounts are positive: what is given back. A unit received `Missing` is not credited.

- **One note per return.** A second `issue()` is refused, naming the first note.
- **A line credited whole by the core is not credited again.** A return of a line one of the core's notes already
  credited is refused, naming that note.
- **The number is taken inside a transaction.** What the note depends on is checked before the number is taken, so a
  refused note never uses one, and again once it is taken, under the series' lock: a note another transaction
  committed meanwhile is seen.
- **An order without a receipt has nothing to credit.**

A note is one row of `phil_commerce_return_credit_note`: `number` its key, `return_id` unique, `order_number` indexed,
`receipt_number`, `issued_at`, `currency`, its `lines` in JSON, `special_discount` and `delivery` in minor units.

## The return slip

```php
$pdf = $entityManager->wrapInTransaction(fn () => $slips->print($return->id, $clock->now(), 'fr'));   // the order's language
```

The slip goes in the parcel of an authorized return: its number, the order and the return, the label's carrier and
tracking number when there is one, then each line's description, units and reason, and what to do with the slip.

| Member of `ReturnSlips` | What it does |
|---|---|
| `slipFor($returnId, $at)` | The `ReturnSlip` of an authorized return; its first call numbers the slip in the series `return_slip` of the core's document register, kept on the return, `slipNumber()`, and later calls keep it |
| `print($returnId, $at, $locale)` | The slip drawn as a PDF by the `ReturnSlipRenderer`, in the locale given |

The series `return_slip` is declared to the core's register by `ReturnDocumentTypes`, with the prefix and digits of
`phil_commerce_returns.slips` — `RS-` and 8 by default —; its numbers follow one another without gaps, so the first
print runs inside a transaction.

`PagedMediaReturnSlips` draws the slip with the native writer of `phil/paged-media` when it is installed: a Letter page,
more when the lines need them, in Helvetica. Its words are the keys `phil_commerce_returns.slip.*` and
`phil_commerce_returns.reason.*` of the domain `phil_commerce_returns`, translated in English and French in
`translations/`; without a translator, it writes English. An application draws its own slip by aliasing
`ReturnSlipRenderer` to its service; without any renderer, `ReturnSlips` is not registered.
