# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceSubscriptionBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_subscription`; `payment_attempts`, `abandon_pending_after_days`, `immediate_downgrades`, `gift_redeem_within_days`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\Offer` | `final class`, Doctrine entity | A tier of a family: price, period, trial, tax category, features, seats |
| `Domain\Model\Subscription` | `final class`, Doctrine entity | A subscriber's subscription: state, periods, awaited order, immediate change, credit carried over, payer |
| `Domain\Model\Gift` | `final class`, Doctrine entity | A subscription offered to someone else, redeemed by a code |
| `Domain\Model\Seat` | `final class`, Doctrine entity | A member holding a seat of an organization's subscription |
| `Domain\ValueObject\BillingPeriod` | `final readonly class` | A count of a unit; period ends counted from an anchor |
| `Domain\ValueObject\ImmediateChange` | `final readonly class` | A change at once awaiting its order: the tier, the time left, the credit; what the new tier is charged |
| `Domain\ValueObject\DunningStage` | `final readonly class` | From so many days past due, every feature, some or none |
| `Domain\Dunning\DunningAccess` | string-backed enum | Full, reduced, none |
| `Domain\ValueObject\StableIdentifier` | `final readonly class` | The identifier of a person or an organization that never changes; refuses an email address |
| `Domain\Gift\GiftRenewal` | string-backed enum | The buyer pays the renewals, or it ends at its term |
| `Domain\Gift\GiftState` | string-backed enum | Pending, paid, redeemed, cancelled |
| `Domain\Offer\BillingUnit` | string-backed enum | Day, week, month, year |
| `Domain\Subscription\SubscriptionState` | string-backed enum | Pending, trialing, active, past due, ended |
| `Domain\Exception\SubscriptionRefused` | `final class`, `DomainError` | A subscription that cannot start, renew, change or end as asked, or a seat refused |
| `Domain\Exception\GiftRefused` | `final class`, `DomainError` | A gift that cannot be bought, paid, redeemed or cancelled as asked |
| `Domain\Repository\OfferRepository` | interface | Finds and stores offers |
| `Domain\Repository\SubscriptionRepository` | interface | Finds and stores subscriptions; those of many subscribers at once, those over their period, the pending ones to order, the pending ones to abandon |
| `Domain\Repository\GiftRepository` | interface | Finds gifts, by id or by the SHA-256 of their code, and stores them |
| `Domain\Repository\SeatRepository` | interface | Finds, counts, stores and removes seats; whether a member holds one of the first |
| `Application\Contract\OrganizationMembership` | interface | The organizations a member belongs to, implemented by the application |
| `Application\OfferSellables` | `final readonly class`, the core's `SellableResolver` | Offers on sale under the type `offer`; a subscription's next period under `subscription`; a gift's periods under `gift` |
| `Application\ProrationOrderProcessor` | `final readonly class`, the core's `OrderProcessor` | The `Proration` adjustments of the lines `subscription:<id>` |
| `Application\OrderedLine` | `final class` | What a line of the module is worth on an order, and the origins of its proration |
| `Application\Subscriptions` | `final readonly class` | Subscribes, orders, records payments, cancels, changes tier at renewal or at once, sweeps |
| `Application\Gifts` | `final readonly class` | Buys, orders, pays, cancels and redeems gifts |
| `Application\Seats` | `final readonly class` | Assigns and releases seats |
| `Application\FeatureAccess` | `final class`, tagged `kernel.reset` | The features a subscriber or a member may use, read once until `forget()` or `reset()` |
| `Infrastructure\Doctrine\DoctrineOfferRepository` | `final readonly class` | Offers through the entity manager |
| `Infrastructure\Doctrine\DoctrineSubscriptionRepository` | `final readonly class` | Subscriptions through the entity manager |
| `Infrastructure\Doctrine\DoctrineGiftRepository` | `final readonly class` | Gifts through the entity manager |
| `Infrastructure\Doctrine\DoctrineSeatRepository` | `final readonly class` | Seats through the entity manager |

See [scope.md](scope.md) for what the module leaves to other packages.
