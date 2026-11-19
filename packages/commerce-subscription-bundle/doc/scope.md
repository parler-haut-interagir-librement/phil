# Scope

Subscriptions for the core of commerce: offers in tiers of a family, billing periods, trials, renewal by an order of
the core, dunning after a failed payment, cancellation at the end of the paid period, changes of tier at the next
renewal or at once with proration, gifts, and the features each tier unlocks — to a subscriber, or to the members of
an organization — free and paid tiers, or support tiers, in one model.

| Part | Content | State |
|---|---|---|
| Offers and tiers | Offers of a family ranked as tiers, sold through the core's `SellableResolver` under the reference type `offer` | Implemented and tested ([offers.md](offers.md)) |
| Periods and trials | Periods counted from an anchor, a trial before the first charge | Implemented and tested ([subscriptions.md](subscriptions.md)) |
| Renewal and dunning | An order of the core per period, retries of a failed payment, past due then ended, cancellation at the end of the paid period; dunning stages per offer keeping every feature, some or none after so many days | Implemented and tested ([dunning.md](dunning.md)) |
| Changes of tier | Within the family, at the next renewal | Implemented and tested |
| Immediate changes with proration | A change taking effect at once, the unused part of the period credited, the credit left over carried to the next orders, as an `OrderProcessor`; a lower tier at the next renewal unless configured | Implemented and tested ([proration.md](proration.md)) |
| Gifts | A buyer pays, a code redeems, the beneficiary named by a stable identifier; renewed at the buyer's charge or ended at its term | Implemented and tested ([gifts.md](gifts.md)) |
| Organizations | A subscription opening its features to every member of an organization, through a port of the application, with optional seats | Implemented and tested ([organizations.md](organizations.md)) |
| Features per tier | `FeatureAccess`, read by the application to open or close a feature | Implemented and tested ([features.md](features.md)) |

## What it leaves to other packages

- **The rights to content** — which page, file or episode a subscriber may read — belong to `phil/content-access`,
  which reads the features this module grants.
- **The benefits owed to each member** — a print each quarter, a name in the credits — belong to
  `phil/benefit-deliverables`.

### The seam with them

The module gives those packages what a subscriber is entitled to, and nothing they would have to undo:

| What they read | Through | Holds |
|---|---|---|
| The features a person may use now | `FeatureAccess::features()` and `grants()` | Their own subscriptions', their organizations' — every member, or the seat holders —, gifts redeemed, and the dunning stages of a renewal past due |
| Who holds a subscription, of which tier, until when | `SubscriptionRepository::forSubscribers()`, `Subscription::offerCode()`, `periodEnd()`, `state()`, `payer()` | The subscriber's stable identifier, never an email address; who pays a gifted one |
| Who in an organization uses it | `SeatRepository::holdsSeat()`, and the application's `OrganizationMembership` | The members holding the first seats of an offer that counts them |

- **A content right is a feature name**: `phil/content-access` maps a page, a file or an episode to a feature
  (`episodes:early`), and asks `FeatureAccess::grants()`; this module never knows what a feature opens.
- **A benefit is owed per period paid**: `phil/benefit-deliverables` reads a subscription's state and period end to
  know who is owed what this quarter, and keeps what it delivered in its own store; this module keeps no delivery.
- **Neither package is required**: this module depends on neither, and neither needs more than the reads above.
- **Organizations themselves**, their members and their roles, belong to the application: the module reads them
  through `OrganizationMembership`, and depends on no package of tenants.

## The ports of the core it implements

- `SellableResolver`, for the reference types `offer`, `subscription` and `gift`: `OfferSellables`, tagged by the
  core's autoconfiguration.
- `OrderProcessor`, for proration: `ProrationOrderProcessor`, tagged after the options and the promotions, before
  the taxes. Its adjustments are of the core's type `Proration`, which the core taxes with the line.

What the core decides stays decided here: prices come from the resolvers, never from a form; every amount is a
`Money` in the order's currency; taxes are computed by the core on each line.
