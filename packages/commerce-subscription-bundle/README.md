# phil/commerce-subscription-bundle

**Recurring offers in tiers, with trials, renewal, dunning, proration, gifts and the features each tier unlocks.**

phil/commerce-subscription-bundle is the subscription module of `phil/commerce-bundle`. It holds offers ranked as
tiers of a family, free and paid tiers or support tiers in one model. It starts subscriptions with or without a
trial, orders each billing period through the core, renews, retries failed payments, and cancels at the end of the
paid period. It changes tier at the next renewal, or at once with the unused part of the period credited. A buyer
offers a subscription to someone else with a code. It tells which features a subscriber may use, and opens an
organization's subscription to its members, with optional seats. A subscription that ships something keeps a
delivery address, frozen in each of its orders and erased some days after the last shipment. With DoctrineBundle,
offers, subscriptions, gifts, seats and delivery addresses are stored.

While a renewal is past due, each offer's dunning stages say what the subscriber keeps after so many days: every
feature by default, some, or none.

It is for an application that sells access by the period, a membership or a plan. Most applications that sell access
need the core and this module only; a catalogue of products belongs to `phil/commerce-catalog-bundle`.

It is a Symfony bundle that refuses to load without the core. Each period is ordered at the offer's current price,
never a price from a form; every amount is a `Money` in the order's currency, and the core taxes each line.

Offers answer the core's `SellableResolver` under the reference type `offer`, so each period is an ordinary order of
the core; a change at once is prorated by an `OrderProcessor` before the core taxes it. The application reads
`FeatureAccess` to open or close a feature for a subscriber.

## Status

| Part | State |
|---|---|
| Offers and tiers, periods counted from an anchor | Implemented and tested |
| Subscriptions: trials, renewal by an order of the core, dunning, cancellation, changes of tier | Implemented and tested |
| Immediate changes with proration | Implemented and tested |
| Gifts redeemed by a code | Implemented and tested |
| Organizations, with optional seats | Implemented and tested |
| Feature access per tier | Implemented and tested |
| Dunning stages per offer: every feature, some or none after so many days past due | Implemented and tested |
| Delivery addresses: on the subscription, frozen in each order, erased after the last shipment, in access and erasure requests | Implemented and tested |
| Storage on Doctrine | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the module beside the core. |
| [Configuration](doc/configuration.md) | The keys, and the services the module registers. |
| [Offers](doc/offers.md) | Offers and tiers, periods, selling through the core, storage. |
| [Subscriptions](doc/subscriptions.md) | States, the loop the application keeps, periods, cancelling, changing tier, storage. |
| [Changing tier at once](doc/proration.md) | What is charged and credited, rounding, which changes are prorated. |
| [Gifts](doc/gifts.md) | Buying, the code, the beneficiary, what happens after the periods paid. |
| [Organizations](doc/organizations.md) | Membership through a port, what a member may use, seats. |
| [Features](doc/features.md) | What a subscriber or a member may use. |
| [Dunning](doc/dunning.md) | What a subscriber keeps while a renewal is past due, stage by stage. |
| [Delivery addresses](doc/delivery-addresses.md) | Where a subscription's shipments go, the copy frozen in each order, the telephone number, the erasure. |
| [Scope](doc/scope.md) | What the module holds, what it leaves to other packages and the seam with them, and the ports of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-subscription-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later; `phil/commerce-bundle` 0.4; `phil/address`, `phil/ddd-bundle` and `phil/money` 0.1; Doctrine ORM 3.7
and DBAL 4.5; `psr/clock` 1.0; `symfony/uid`, `symfony/config` and `symfony/dependency-injection` 8.2 or later.
Optional: `symfony/console`, `symfony/scheduler` and `symfony/lock` for the purge of the delivery addresses, and
`phil/privacy-bundle` 0.3 for their access and erasure requests.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
