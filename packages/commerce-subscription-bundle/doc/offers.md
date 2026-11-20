# Offers

An offer is one tier of a family: a free tier, paid tiers and support tiers are offers of one family, ranked, some
at a price of zero. A subscription changes tier within its family.

```php
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\Money\Currency;
use Phil\Money\Money;

$offers->add(Offer::create('free', 'studio', 0, 'Free', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['projects:3']));
$offers->add(Offer::create('pro', 'studio', 1, 'Pro', Money::of(999, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 14, 'digital', ['projects:unlimited', 'export']));
```

| Argument of `Offer::create()` | Rule |
|---|---|
| `code`, `family` | Up to 64 lowercase letters, digits, `_`, `.` or `-`, starting with a letter or digit |
| `rank` | 0 or more: the order of the tiers in the family |
| `name` | Not blank: the description of the order line |
| `price` | Zero or more, per period; zero makes the tier free |
| `period` | `BillingPeriod::of(BillingUnit, int $count)`: 1 to 36 days, weeks, months or years |
| `trialDays` | 0 to 365 |
| `taxCategory` | Not empty: the category the core's tax rates read |
| `features` | Names of up to 128 lowercase letters, digits, `_`, `.`, `:` or `-`; duplicates dropped |
| `seats` | `null`, the default, for every member of an organization holding it; or 1 to 100,000 seats ([organizations.md](organizations.md)) |

- **`reprice(Money)`** sets the price of the periods ordered from now on, in the offer's currency; periods already
  paid keep theirs.
- **`withdraw()`** stops new subscriptions and changes to the tier, and takes it off sale: no cart sells it any
  more. The subscriptions to it go on, with its features, and keep renewing at its price.
- **Sold through the core**: `OfferSellables` answers the references of type `offer` — `offer:pro` is one period of
  `pro`, at its current price, in its tax category, while `pro` is on sale — and those of type `subscription` —
  `subscription:<id>` is the next period of that subscription, at its renewal tier, withdrawn or not, while the
  subscription awaits no order — the new tier during a change at once ([proration.md](proration.md)) —, and those of
  type `gift` — `gift:<id>` is one period of that gift's offer, a unit per period offered, while the gift awaits its
  order ([gifts.md](gifts.md)).

## Periods

**Periods are counted from an anchor, never chained.** The end of the n-th period is the anchor plus n periods, so a
monthly subscription started on January 31st ends on February 28th, then March 31st, rather than drifting to the
28th. A year from February 29th ends on February 28th, and comes back to the 29th four years later. The time and the
time zone of the anchor are kept.

## Storage

With DoctrineBundle, an offer is one row of `phil_commerce_subscription_offer`.

| Column | Type | Holds |
|---|---|---|
| `code` | `VARCHAR(64)`, primary key | The offer's code |
| `family` | `VARCHAR(64)`, indexed | Its family |
| `tier_rank` | `SMALLINT` | Its rank in the family |
| `name` | `VARCHAR(255)` | Its name |
| `price`, `currency` | `BIGINT`, `CHAR(3)` | Its price per period, in minor units |
| `period_unit`, `period_count` | `VARCHAR(8)`, `SMALLINT` | Its period |
| `trial_days` | `SMALLINT` | Its trial |
| `tax_category` | `VARCHAR(64)` | Its tax category |
| `features` | JSON | The features it unlocks |
| `active` | `BOOLEAN` | `false` once withdrawn |
| `seats` | `INTEGER`, nullable | The seats it counts; `null` for every member |
| `dunning` | JSON | Its dunning stages ([dunning.md](dunning.md)) |
