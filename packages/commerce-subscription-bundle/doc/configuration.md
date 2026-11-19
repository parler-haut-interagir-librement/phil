# Configuration

The extension alias is `phil_commerce_subscription`.

```yaml
# config/packages/phil_commerce_subscription.yaml
phil_commerce_subscription:
    payment_attempts: 3             # the default
    abandon_pending_after_days: 7   # the default
    immediate_downgrades: false     # the default
    gift_redeem_within_days: 365    # the default
```

| Key | Type | Default | Effect |
|---|---|---|---|
| `payment_attempts` | int, 1 to 10 | `3` | How many failed payments of a renewal end a subscription; it stays past due, with access, until then |
| `abandon_pending_after_days` | int, 1 to 365 | `7` | How many days a subscription whose first period was never paid stays pending before `sweep()` ends it |
| `immediate_downgrades` | bool | `false` | Whether `changeNow()` to a lower tier takes effect at once, prorated, rather than at the next renewal |
| `gift_redeem_within_days` | int, 1 to 3650 | `365` | How many days the code of a paid gift redeems it |

## What the module registers

| Service | Role |
|---|---|
| `Application\OfferSellables` | Sells offers on sale to the core under the reference type `offer`, the next period of a subscription under `subscription`, and a gift's periods under `gift`; tagged by the core's autoconfiguration |
| `Application\ProrationOrderProcessor` | Prorates the lines `subscription:<id>`; tagged `phil_commerce.order_processor` after the options and the promotions, before the taxes |
| `Application\Subscriptions` (public) | Subscribes, orders, records payments, cancels, changes tier at renewal or at once, sweeps; tells `FeatureAccess` of each change |
| `Application\Gifts` (public) | Buys, orders, pays, cancels and redeems gifts |
| `Application\Seats` (public) | Assigns and releases the seats of an organization's subscription |
| `Application\FeatureAccess` (public, tagged `kernel.reset`) | The features a subscriber may use, read once per subscriber until a change or a reset; receives the application's `OrganizationMembership` when it declares one, and the container's `clock` for the dunning stages |
| `Infrastructure\Doctrine\DoctrineOfferRepository`, aliased (public) as `OfferRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineSubscriptionRepository`, aliased (public) as `SubscriptionRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineGiftRepository`, aliased (public) as `GiftRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineSeatRepository`, aliased (public) as `SeatRepository` | With DoctrineBundle only |

With DoctrineBundle, the module prepends the attribute mapping of `Domain\Model`. Generate a migration for the
tables `phil_commerce_subscription_offer`, `phil_commerce_subscription`, `phil_commerce_subscription_gift` and
`phil_commerce_subscription_seat`.
