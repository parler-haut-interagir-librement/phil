# Organizations

A subscription held by an organization opens its features to **every member of the organization**, or, when its
offer counts **seats**, to the members holding one. The module knows no organization: the application tells which
organizations a member belongs to, through a port.

## Telling who belongs where

```php
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;

final readonly class Memberships implements OrganizationMembership
{
    public function __construct(private MemberRepository $members)
    {
    }

    public function organizationsOf(string $member): array
    {
        return $this->members->organizationIdsOf($member);
    }
}
```

- The organization subscribes as any subscriber, under its stable identifier:
  `$subscriptions->subscribe($organizationId, 'crew', $clock->now())`.
- `organizationsOf()` returns the stable identifiers of the member's organizations, as their subscriptions name their
  subscriber; the first 100 are read.
- A service implementing `OrganizationMembership` is the one `FeatureAccess` receives. Without one, a subscriber's
  features are those of their own subscriptions.
- The module depends on no package of tenants or organizations: any model of the application fits.

## What a member may use

`FeatureAccess::features($member)` gives the features of the member's own subscriptions first, then those of their
organizations' subscriptions granting access. Everything is read in one query for the subscriptions, one for the
offers, and one for each seat to check.

When a member joins or leaves an organization, the application calls `FeatureAccess::forget($member)`. A change of an
organization's subscription forgets the members read through it.

## Seats

```php
$offers->add(Offer::create('crew', 'studio', 3, 'Crew', Money::of(4_900, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['crew:board'], 5));

$seats->assign($subscription, $memberId, $clock->now());   // refused once the 5 seats are taken
$seats->release($subscription, $memberId);
$seats->holders($subscription);                            // earliest assigned first
```

- **An offer without seats** opens its features to every member, and takes none.
- **An offer with seats** opens them to the members holding one of its first seats, earliest assigned first. The
  organization itself needs no seat.
- **Changed to a tier with fewer seats**, the earliest seats keep their features and the later ones wait, until seats
  are released or added; no seat is taken from anyone.
- Who may assign or release a seat is the application's to decide. A member is named by a stable identifier, never an
  email address.

## Storage

With DoctrineBundle, a seat is one row of `phil_commerce_subscription_seat`, and an offer's seats a column of
`phil_commerce_subscription_offer`.

| Column | Type | Holds |
|---|---|---|
| `subscription`, `member` | `CHAR(36)`, `VARCHAR(255)`, primary key; `member` indexed | The organization's subscription, and the member holding the seat |
| `assigned_at` | `DATETIME` | When the seat was assigned |
| `seats`, of the offers | `INTEGER`, nullable | How many seats the offer counts; `null` for every member |
