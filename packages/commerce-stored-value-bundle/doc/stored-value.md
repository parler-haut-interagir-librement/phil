# Stored value

Two instruments hold value in one currency: a **gift card**, known by its code, to whoever holds it, and a customer's
**store credit**, known by the customer. Their balances move only through movements, and never go below zero.

## Gift cards

### Selling one

A gift card is a sellable of the core, type `gift_card`, whose identifier is its value:

```php
$orderModifier->add($order, GiftCardSellables::reference(Money::of(5_000, Currency::of('CAD'))), 1);
```

The line reads `Gift card CAD 50.00`, costs its value, and takes the configured tax category. A value above
`max_value` is not sold. Once the order is placed and fully paid, the application issues its cards:

```php
foreach ($giftCards->issueFor($order, $clock->now()) as $issued) {
    $mailer->send(/* $issued->code->toString(): 7KQ2-M9XA-3TFD-W8HC */);
}
```

One card per unit, at the face value of the line, whatever promotion lowered its price. **An order with anything
left to pay issues no card**: `issueFor()` throws `StoredValueRefused` while the core's `Payments::summary()` still
finds something payable — a pay-later or on-account payment counts once completed. **Issuing twice changes nothing**,
even from two servers at the same moment — see [Value created once](#value-created-once). The code is known only at
that moment; afterwards the module keeps its hash and its last four characters.

`issueFor()` returns a `GiftCardIssuance`: going through it goes through the cards issued by this call, each with its
code; its `issued` property lists them, and its `cards` property lists every card of the order, issued now or before.

### Cancelling an order that sold cards

```php
$order->cancel($clock->now());
$giftCards->voidFor($order->number, $clock->now(), 'Order cancelled');   // before refunding its payments
```

`voidFor()` voids the order's cards still active and returns them; each loses what it held, in a `Voided` movement.
What a card already spent is gone: its movements tell how much, and the application deducts it from what it refunds
of the order.

### Giving one, loading one, voiding one

```php
$sold = $giftCards->issue(Money::of(2_500, Currency::of('CAD')), $clock->now());                  // paid at the counter
$given = $giftCards->give(Money::of(1_000, Currency::of('CAD')), $clock->now(), $clock->now()->modify('+90 days'));
$giftCards->load('7KQ2-M9XA-3TFD-W8HC', Money::of(1_000, Currency::of('CAD')), $clock->now(), 'Top-up');
$giftCards->void('7KQ2-M9XA-3TFD-W8HC', $clock->now(), 'Reported stolen');
```

A voided card loses what it held and is used no more.

### Given or paid

Every card knows where its value comes from, in `Instrument::$origin`:

| Origin | How it is issued | Expiry |
|---|---|---|
| `GiftCardOrigin::Given` | `give()`, or `issue(..., origin: GiftCardOrigin::Given)`: a promotion, a gesture, a prize | Allowed |
| `GiftCardOrigin::Paid` | `issue()`, and `issueFor()` for the cards an order sold | **Refused**, unless the card is marked for a specific service |

A paid card for a specific service — a meal, a show, a treatment — is issued with `specificService: true`, and may
then carry an expiry:

```php
$giftCards->issue(Money::of(9_000, Currency::of('CAD')), $clock->now(), $clock->now()->modify('+1 year'), specificService: true);
```

A paid card given an expiry without being for a specific service is refused with `InvalidValue`. A store credit has no
origin.

**These rules are the bundle's safe default, not legal advice: each shop checks the law that applies where it sells**
before it gives any card an expiry — consumer protection laws differ by country, province and state, and some refuse
an expiry even on a card given for free or for a specific service. The bundle never sets an expiry on its own.

### Printing one

`GiftCardPdf` prints a card just issued on one page the size of a payment card (ID-1, 85.6 by 53.98 mm), with the
native PDF writer of `phil/paged-media`: the shop's name, the value and its currency, the code, and below them that the
card is for a specific service and when it expires, when it does. The code is known only while the card is just
issued, so the card is printed then:

```php
use Phil\CommerceStoredValueBundle\Infrastructure\Pdf\GiftCardPdf;

$printer = new GiftCardPdf('The Shop');
$pdf = $printer->render($giftCards->issue(Money::of(5_000, Currency::of('CAD')), $clock->now()));
```

The labels are English by default — `gift_card`, `valid_until` (with `%s` for the date) and `specific_service` —; an
application printing in another language passes its own. Helvetica prints the Western languages; a shop whose name
needs any other script passes a TrueType font and its bold. A shop name too long for the card is cut at the last word
that fits.

### Codes

Sixteen characters of Crockford's Base32 — no I, L, O or U —, in four groups. Reading forgives what a person types:
lowercase, spaces, hyphens, and I, L and O read as 1, 1 and 0. Eighty bits of chance put guessing out of reach **as
long as the application limits how often a code may be tried**, on the balance page as at the checkout.

## Store credit

```php
$storeCredits->grant('customer-42', Money::of(1_500, Currency::of('CAD')), $clock->now(), 'Return of A-0042', 'A-0042');
$storeCredits->balance('customer-42', Currency::of('CAD'));
```

One balance per customer and currency, opened the first time credit is granted. A customer is named by whatever the
application uses — an account, a tenant, an email.

## Paying with stored value

```php
$payment = $storedValuePayments->payWithGiftCard($order, '7KQ2-M9XA-3TFD-W8HC', $clock->now());
$payment = $storedValuePayments->payWithStoreCredit($order, 'customer-42', $clock->now(), Money::of(1_000, Currency::of('CAD')));
```

Each call is one payment of the core, method `gift_card` or `store_credit`: created, the value spent, and the payment
completed in the same transaction. Without an amount, it pays as much of what is left as the instrument holds; the
rest is paid by another method, for a split payment. A voided or expired instrument, one with nothing left, and more
than the instrument holds are all refused before the payment exists, so that a refusal leaves no payment behind.
Each payment is its own transaction: the core reads what is left to pay from the payments already stored.

## Refunds

The core's `Payments::refund()` reaches `StoredValueGateway`, which gives the amount back to the instrument it was spent
from and records the refund at once, under its idempotency key: a refund asked again under the same key gives nothing
more. Refunding to store credit what was paid otherwise is a return's affair: `StoreCredits::grant()`.

A gift card voided or expired since it paid takes no value back. The refund then becomes the store credit of the
order's customer, a `Restored` movement on that credit — opened if need be. The module knows a card by its code, not
by its holder: the application names the customer by implementing `Application\Port\StoreCreditHolders`, which the
gateway receives by autowiring.

```php
final class OrderCustomers implements StoreCreditHolders
{
    public function holderOf(string $orderNumber): ?string
    {
        return $this->orders->customerOf($orderNumber);   // whatever the application keeps
    }
}
```

Without that service, or for an order whose customer is unknown, such a refund throws `StoredValueRefused`, as does a
refund to a store credit voided since; the application refunds by another means.

## Value created once

A gift card issued twice for one order, or credit granted twice for one return, is value given away. The module keeps
both from happening, in the database rather than in memory, so that the guarantee holds across processes and servers.

### Issuing the cards of an order

`issueFor()` stores the cards it issues at once, all or none, in a transaction of their own — or in a savepoint of the
caller's transaction when one is open. Each card's `Issued` movement carries its rank among the order's cards, from 0,
in the order of the lines; a unique constraint on the order number, the kind of movement and that rank refuses a second
card at the same rank.

Two requests issuing the cards of the same order at the same moment — a payment notification and its replay, two
workers taking the same message — both count the cards already issued, both find none, and both try to store them.
The database keeps the first; the second is refused, stores nothing, and returns the cards of the first in
`GiftCardIssuance::$cards`, with nothing in `issued`: it has no code to send, and sends none.

```php
$issuance = $giftCards->issueFor($order, $clock->now());

foreach ($issuance as $issued) {          // nothing when another request issued them first
    $mailer->send(/* $issued->code->toString() */);
}

$issuance->cards;                         // every card of the order, whoever issued it
```

The refusal does not reach the application's entity manager: the cards are stored by an entity manager of their own on
the same connection, so what the application holds stays managed, and its own flush goes on. The cards returned are
the ones the application's entity manager reads.

A card issued at the counter with `issue($value, $at, null, $orderNumber)` carries no rank and is not bound by the
constraint; it still counts among the cards of the order that `issueFor()` finds already issued.

### An idempotency key on a load or a grant

`GiftCards::load()` and `StoreCredits::grant()` take an optional idempotency key, their last argument:

```php
$giftCards->load($code, Money::of(1_000, Currency::of('CAD')), $clock->now(), 'Top-up', idempotencyKey: $messageId);
$storeCredits->grant('customer-42', Money::of(1_500, Currency::of('CAD')), $clock->now(), 'Return of A-0042', 'A-0042', idempotencyKey: 'return:'.$returnId);
```

Asked again with the same key on the same instrument, the operation adds nothing: `load()` returns the card as it is,
`grant()` returns the movement first recorded. Another key, or no key, records another movement. The same amount is
expected: a key replayed with another amount is a mistake, refused with `InvalidValue`. A key is 1 to 128 characters,
without surrounding spaces, and is kept on the movement; a unique constraint on the instrument and the key refuses a
second movement with the same key, so that two requests with the same key at the same moment record one movement —
the flush of the second throws Doctrine's `UniqueConstraintViolationException`, or `OptimisticLockException` when the
instrument's version moved first, and its retry finds the movement.

The key is the operation's, chosen so that a retry of the same operation carries the same key: the identifier of the
return that grants the credit, of the purchase that loads the card, of the message that asks for it.

### Replaying a Messenger message

A message handled twice — retried after a failure, delivered twice by the transport, or replayed by whoever can write
to the transport — runs its handler twice. Signing a message proves who sent it, not that it is new: a replayed
message carries a valid signature. The handler is made safe to run again instead:

```php
#[AsMessageHandler]
final readonly class GrantReturnCreditHandler
{
    public function __invoke(GrantReturnCredit $message): void
    {
        $return = $this->returns->get($message->returnId);   // the message carries identifiers only

        $this->storeCredits->grant($return->customer, $return->credit, $this->clock->now(), null, $return->orderNumber, idempotencyKey: 'return:'.$message->returnId);
    }
}
```

A handler issuing the cards of an order needs no key: `issueFor()` is idempotent by the order itself.

## Movements

Every change is a movement: issued, loaded, granted, redeemed, restored, voided — its amount, positive when added, the
balance it left, the order and the payment it concerns, a note. Movements are never changed nor removed;
`InstrumentRepository::movementsOf()` lists an instrument's.
