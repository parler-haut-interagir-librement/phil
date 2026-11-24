# Changelog

All notable changes to `phil/commerce-subscription-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8), and of each development requirement
  (`doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

## 0.1.0-alpha - 2026-11-20

### Added

- `Offer`, `BillingPeriod` and `BillingUnit`: tiers of a family, periods counted from an anchor; `Offer::$seats`, the
  seats an organization's subscription counts, or `null` for every member.
- Dunning stages per offer: `DunningStage` (`full()`, `reduced()`, `cut()`), `DunningAccess`, `Offer::changeDunning()`,
  `dunningStages()` and `featuresPastDue()`, column `dunning`; every feature while the retries last without stages;
  `Subscription::pastDueSince()` and `daysPastDue()`, column `past_due_since`.
- `Subscription` and `SubscriptionState`: trials, renewal by an order of the core, dunning, cancellation at the end
  of the paid period, changes of tier at renewal; `SubscriptionRefused`. `Subscription::awaitsAnOrder()` and
  `abandon()`. A trial whose subscriber changes to a free tier becomes active when it ends. A subscription cancelled
  while its renewal's payment is in flight waits for that payment: paid, the period is granted and the subscription
  ends at its end; failed, it ends at once. A change of tier refuses a tier priced in another currency.
- Changes of tier at once with proration: `Subscriptions::changeNow()`, `Subscription::startImmediateChange()`,
  `awaitChangePayment()`, `abandonChange()`, `switchDuringTrial()`, `pendingChange()`, `periodStart()`,
  `periodAmount()`, `carriedCredit()` and `currency()`; `ImmediateChange`, the share of the time left by
  `Money::allocate()` in seconds; `ProrationOrderProcessor`, the core's `OrderProcessor`, whose `Proration`
  adjustments `proration:time`, `proration:credit` and `proration:carried` the core taxes with the line; `OrderedLine`;
  the credit a change does not use carried over to the next orders; a trial switching tier without an order; the day
  of the renewal renewing at the new tier; a lower tier at the next renewal unless `immediate_downgrades`; the failed
  payment of a change giving it up, never past due; `SubscriptionRefused::otherCurrency()` and `periodOver()`.
- Gifts: `Gift`, `GiftState`, `GiftRenewal`, `GiftRefused`, `Gifts` (`buy()`, `order()`, `paid()` returning a code
  shown once, `paymentFailed()`, `reissueCode()`, `cancel()`, `redeem()`), the reference type `gift`,
  `OfferSellables::GIFT_TYPE`; codes of 80 random bits in Crockford's base 32, stored as their SHA-256 and refused
  alike whatever the reason; `Subscription::fromGift()` and `payer()`, renewed at the buyer's charge or ended at its
  term; `StableIdentifier`, which refuses an email address for a buyer, a beneficiary or a member.
- Organizations: the port `Application\Contract\OrganizationMembership`, which `FeatureAccess` reads to open an
  organization's subscriptions to its members; `Seat`, `Seats` (`assign()`, `release()`, `holders()`), the earliest
  seats keeping their features when a tier counts fewer; `SubscriptionRefused::withoutSeats()` and `seatsTaken()`.
- `OfferSellables`, the core's `SellableResolver` for the type `offer`, and the reference type `subscription`,
  `OfferSellables::SUBSCRIPTION_TYPE`; a withdrawn offer is not sold to a cart, and its subscriptions keep renewing.
- `Subscriptions`: `order()` places a line `subscription:<id>`; `sweep()` takes a `refused` callable and a `limit`,
  goes on past a subscription it cannot handle, and ends a subscription whose first period was never paid after
  `abandon_pending_after_days`.
- `FeatureAccess`, which gives a subscription past due what its offer's dunning stages keep, on a PSR-20 clock, and
  reads a subscriber's features once, with those of their organizations, until `Subscriptions`, `Gifts` or `Seats`
  changes one of their subscriptions or seats, or the service is reset; `forget()`, which forgets an organization's
  members with it, and `reset()`, and the `kernel.reset` tag on the service.
- `OfferRepository`, with `findByCodes()`, which `FeatureAccess` reads the offers of a subscriber's subscriptions
  with, in one query; `SubscriptionRepository`, with `forSubscribers()`, `pendingWithoutOrder()`,
  `pendingCreatedBefore()` and `periodOverBy()`, which reads no pending subscription, each with a `limit`;
  `GiftRepository`, with `findByCodeHash()`; `SeatRepository`, with `holdsSeat()`, which counts the seats assigned
  before the member's; their Doctrine adapters.
- `PhilCommerceSubscriptionBundle`, extension alias `phil_commerce_subscription`, which refuses to load without
  `PhilCommerceBundle`; the keys `payment_attempts`, `abandon_pending_after_days` (7 by default),
  `immediate_downgrades` (`false` by default) and `gift_redeem_within_days` (365 by default).
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README; its French mirror in `README.fr.md` and
  `doc/fr/`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
