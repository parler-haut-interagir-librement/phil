# Gifts

A gift is a subscription offered to someone else. **The buyer pays** one or more periods of an offer, by an order of
the core; the payment issues a **code**, and whoever redeems it starts a subscription.

```php
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;

// The buyer offers three months
$gift = $gifts->buy($buyerId, 'pro', 3, GiftRenewal::EndsAtTerm, $clock->now());
$order = $gifts->order($gift, $orderNumbers->next(), $taxZoneOf($buyerId), $clock->now());
$orders->add($order);                                      // the core's OrderRepository
[$payment, $start] = $payments->start($order, 'card', $clock->now());

// When the core applied the payment's notification
$code = $gifts->paid($gift, $order->number, $clock->now());   // shown once: hand it over now
$gifts->paymentFailed($gift, $order->number);                  // or wait for another order

// The beneficiary redeems it, typed or from a link
$subscription = $gifts->redeem($code, $beneficiaryId, $clock->now());
```

## Buying and paying

- **`buy(buyer, offerCode, periods, renewal, at)`** records a gift of 1 to 36 periods of a paid offer on sale. A free
  or withdrawn offer is refused.
- **`order(gift, number, taxZone, at)`** places an order of the core with one line `gift:<id>`, one unit per period,
  at the offer's current price, taxed by the core in the buyer's zone, and records it as awaited.
- **`paid(gift, orderNumber, at)`** makes the gift redeemable for `gift_redeem_within_days` (365 by default) and
  returns its code. **The code is shown once**: the module keeps only its SHA-256. The application hands it over at
  once — by email, or in a link it builds — and never puts it in a message of a queue.
- **`paymentFailed(gift, orderNumber)`** leaves the gift waiting for another order.
- **`reissueCode(gift)`** replaces a lost code; the old one no longer redeems the gift.
- **`cancel(gift, at)`** gives up a gift not redeemed yet, or expired; refunding what was paid is the payment's affair.

## The code

A code is 16 characters of Crockford's base 32 — 80 random bits —, shown in four groups: `7KQ2-M9XD-4TPA-ZR6B`. It
is read as typed: in any case, with or without dashes and spaces, `O` read as `0`, `I` and `L` as `1`.

**A code unknown, malformed, already used, replaced or expired is refused the same way**, `This code redeems no gift.`,
so that nothing tells a guess from a code that once was. A malformed code reads nothing. The attempts are the
application's to limit, with Symfony's rate limiter on the redemption route.

## The beneficiary

**The buyer and the beneficiary are named by a stable identifier**: the immutable identifier of their account, or
the `sub` claim of their identity provider — **never the identifier they sign in with**, an email address or a
username, which they may change and someone else may take after them. `StableIdentifier` refuses anything with `@`.

`redeem(code, beneficiary, at)` starts the beneficiary's subscription, active at once for the periods paid, whatever
the offer's trial, even if the offer was withdrawn since. Its `periodAmount()` is one period's share of what the buyer
paid, which a later change of tier credits from.

## When the periods paid are over

| `GiftRenewal` | The subscription |
|---|---|
| `BuyerPays` | Renews as any subscription: the sweep returns it, and its renewals are ordered for the buyer to pay — the buyer is its `payer()` |
| `EndsAtTerm` | Is cancelled at the end of the periods paid; the beneficiary may resume it, at their own charge |

## Storage

With DoctrineBundle, a gift is one row of `phil_commerce_subscription_gift`.

| Column | Type | Holds |
|---|---|---|
| `id` | `CHAR(36)`, primary key | A time-ordered UUID (v7) |
| `buyer` | `VARCHAR(255)`, indexed | The buyer's stable identifier |
| `offer`, `periods`, `renewal` | `VARCHAR(64)`, `SMALLINT`, `VARCHAR(16)` | What is offered, and what happens after |
| `state` | `VARCHAR(16)` | Pending, paid, redeemed or cancelled |
| `code_hash` | `CHAR(64)`, unique, nullable | The SHA-256 of the current code |
| `awaited_order` | `VARCHAR(64)`, nullable | The order of the core whose payment is awaited |
| `period_amount`, `currency` | `BIGINT`, `CHAR(3)` | One period's share of what was paid |
| `redeemable_until` | `DATETIME`, nullable | When the code stops redeeming it |
| `beneficiary`, `subscription` | `VARCHAR(255)`, `CHAR(36)`, nullable | Who redeemed it, and the subscription it started |
| `created_at`, `closed_at` | `DATETIME` | When it was bought, and redeemed or cancelled |
| `version` | `INTEGER` | Moved on by each write |

A subscription started by a gift keeps its buyer in the column `payer`, `VARCHAR(255)`, nullable, of
`phil_commerce_subscription`.
