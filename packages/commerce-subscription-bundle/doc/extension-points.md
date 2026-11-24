# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application configures or relies on. The ports of the
core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag |
|---|---|---|
| `SellableResolver` | `OfferSellables`, for the reference types `offer`, `subscription` and `gift` | `phil_commerce.sellable_resolver`, by the core's autoconfiguration |
| `OrderProcessor` | `ProrationOrderProcessor`, the `Proration` adjustments of a change at once and of the credit carried over | `phil_commerce.order_processor`, after `OptionSupplementProcessor` and the catalogue's promotions, before `TaxOrderProcessor` |

Prices come from the resolvers, every amount is a `Money` in the order's currency, and taxes are computed by the core
on each line. See [Scope](scope.md).

## Features

The features an offer lists are the application's names: `FeatureAccess::features()` and `grants()` tell what a
subscriber may use, and a feature flag that depends on a tier reads `grants()`. `FeatureAccess::forget()` drops what
it read of a subscriber whose subscription changed outside `Subscriptions`, or of a member who joined or left an
organization. See [Features](features.md).

## Organizations

The application implements `Application\Contract\OrganizationMembership`: `organizationsOf(member)` returns the
stable identifiers of the organizations a member belongs to. Declared as a service, it is the one `FeatureAccess`
receives; without it, no organization opens its features. See [Organizations](organizations.md).

## Delivery addresses

The application implements `Application\Contract\PhoneRequirement` when its carriers ask for a telephone number by
other rules than a list of delivery methods, and aliases it under that interface. Another package reads a delivery
address through `DeliveryAddresses::of()` and `ofOrder()`, behind a port of its own. See
[Delivery addresses](delivery-addresses.md).

## Repositories

`OfferRepository`, `SubscriptionRepository`, `GiftRepository`, `SeatRepository` and `DeliveryAddressRepository` are
public aliases of their Doctrine adapters; a store of the application's own keeps `findByCodes()` and `forSubscribers()` reading many rows at
once, the sweeps — `periodOverBy()`, `pendingWithoutOrder()`, `pendingCreatedBefore()` — bounded by their limit,
`findByCodeHash()` on an index, `holdsSeat()` counting the seats assigned before the member's rather than reading
them, and `DeliveryAddressRepository::erase()` and `purge()` idempotent, writing at once, each purge batch bounded by
its limit.

## Configuration

| Option | What it extends |
|---|---|
| `payment_attempts` | How many failed payments of a renewal end a subscription |
| `abandon_pending_after_days` | How many days an unpaid first period stays pending before the sweep ends it |
| `immediate_downgrades` | Whether a change at once to a lower tier is prorated now rather than at the next renewal |
| `gift_redeem_within_days` | How many days a gift's code redeems it |
| `delivery_addresses` | How long delivery addresses are kept, which carriers ask for a telephone number, how the purge runs |

See [Configuration](configuration.md).

## Services

| Service | How an application relies on it |
|---|---|
| `Subscriptions` | Subscribes, renews, records payments and failures, cancels, changes tier, sweeps; public — see [Subscriptions](subscriptions.md) and [Changing tier at once](proration.md) |
| `Gifts` | Gifts bought, paid and redeemed by a code; public — see [Gifts](gifts.md) |
| `Seats` | The seats of an organization's subscription; public — see [Organizations](organizations.md) |
| `FeatureAccess` | What a subscriber or a member may use; public, reset between requests |
| `DeliveryAddresses` | Delivery addresses, their copies in each order, their erasure and purge; public — see [Delivery addresses](delivery-addresses.md) |
