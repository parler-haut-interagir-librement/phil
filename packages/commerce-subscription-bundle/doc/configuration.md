# Configuration

The extension alias is `phil_commerce_subscription`.

```yaml
# config/packages/phil_commerce_subscription.yaml
phil_commerce_subscription:
    payment_attempts: 3             # the default
    abandon_pending_after_days: 7   # the default
    immediate_downgrades: false     # the default
    gift_redeem_within_days: 365    # the default
    delivery_addresses:
        retention_days: 90          # the default
        phone_required_methods: []  # the default
        purge_batch: 500            # the default
        schedule:
            enabled: true           # the default, with symfony/scheduler
            frequency: '1 day'      # the default
        lock_factory: lock.factory  # the default; null for none
```

| Key | Type | Default | Effect |
|---|---|---|---|
| `payment_attempts` | int, 1 to 10 | `3` | How many failed payments of a renewal end a subscription; it stays past due, with access, until then |
| `abandon_pending_after_days` | int, 1 to 365 | `7` | How many days a subscription whose first period was never paid stays pending before `sweep()` ends it |
| `immediate_downgrades` | bool | `false` | Whether `changeNow()` to a lower tier takes effect at once, prorated, rather than at the next renewal |
| `gift_redeem_within_days` | int, 1 to 3650 | `365` | How many days the code of a paid gift redeems it |
| `delivery_addresses.retention_days` | int, 1 to 3650 | `90` | How many days after the last shipment a delivery address is erased: once the subscription ended for its own, once the shipment left for a copy |
| `delivery_addresses.phone_required_methods` | list of strings | `[]` | The delivery methods whose carrier asks for a telephone number; every other method keeps none |
| `delivery_addresses.purge_batch` | int, 1 to 10000 | `500` | How many addresses and copies each batch of a purge erases |
| `delivery_addresses.schedule.enabled` | bool | `true` | Whether the recurring purge is scheduled, with `symfony/scheduler` |
| `delivery_addresses.schedule.frequency` | string | `'1 day'` | How often the purge runs, as `RecurringMessage::every()` reads it |
| `delivery_addresses.lock_factory` | service id or `null` | `'lock.factory'` | The `LockFactory` the scheduled purge and the command run under, when the service exists; `null` for none |

## What the module registers

| Service | Role |
|---|---|
| `Application\OfferSellables` | Sells offers on sale to the core under the reference type `offer`, the next period of a subscription under `subscription`, and a gift's periods under `gift`; tagged by the core's autoconfiguration |
| `Application\ProrationOrderProcessor` | Prorates the lines `subscription:<id>`; tagged `phil_commerce.order_processor` after the options and the promotions, before the taxes |
| `Application\Subscriptions` (public) | Subscribes, orders, records payments, cancels, changes tier at renewal or at once, sweeps; tells `FeatureAccess` of each change |
| `Application\Gifts` (public) | Buys, orders, pays, cancels and redeems gifts |
| `Application\Seats` (public) | Assigns and releases the seats of an organization's subscription |
| `Application\FeatureAccess` (public, tagged `kernel.reset`) | The features a subscriber may use, read once per subscriber until a change or a reset; receives the application's `OrganizationMembership` when it declares one, and the container's `clock` for the dunning stages |
| `Application\DeliveryAddresses` (public) | The delivery addresses of the subscriptions and their copies in each order, their erasure and purge; given to `Subscriptions`, which freezes the address of each order it places |
| `Application\ConfiguredPhoneRequirement`, aliased as `PhoneRequirement` | The delivery methods of `phone_required_methods` |
| `Application\Handler\PurgeDeliveryAddressesHandler` | Handles `PurgeDeliveryAddresses`, tagged `messenger.message_handler`, at the time of the container's `clock` |
| `Infrastructure\Console\PurgeDeliveryAddressesCommand` | `phil:commerce-subscription:purge-addresses`, with `symfony/console` |
| `Infrastructure\Scheduler\DeliveryAddressSchedule` | The schedule `phil_commerce_subscription_addresses`, with `symfony/scheduler`, under the lock factory when it exists |
| `Infrastructure\Privacy\DeliveryAddressPrivacy` | Tagged `phil_privacy.exporter` and `phil_privacy.eraser`, with `phil/privacy-bundle` registered |
| `Infrastructure\Doctrine\DoctrineOfferRepository`, aliased (public) as `OfferRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineSubscriptionRepository`, aliased (public) as `SubscriptionRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineGiftRepository`, aliased (public) as `GiftRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineSeatRepository`, aliased (public) as `SeatRepository` | With DoctrineBundle only |
| `Infrastructure\Doctrine\DoctrineDeliveryAddressRepository`, aliased (public) as `DeliveryAddressRepository` | With DoctrineBundle only |

With DoctrineBundle, the module prepends the attribute mapping of `Domain\Model`. Generate a migration for the
tables `phil_commerce_subscription_offer`, `phil_commerce_subscription`, `phil_commerce_subscription_gift`,
`phil_commerce_subscription_seat` and `phil_commerce_subscription_order_address`. The columns `delivery_address` and
`last_shipped_at` of `phil_commerce_subscription` are nullable, so the migration that adds them to an existing table
leaves its rows as they are. Without DoctrineBundle, every service reads a repository: the application declares
them itself, with its own adapters.
