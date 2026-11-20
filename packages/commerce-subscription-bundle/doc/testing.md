# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `BillingPeriod` | Days, weeks, months and years from an anchor; month ends and leap years; the anchor's time zone kept; bounds |
| `Offer` | Every rule of `create()`, seats included, repricing, withdrawal |
| `Subscription` | Each start, payment, failure, cancellation, resumption, change of tier and expiry, with the refusals of each; renewals counted from the anchor; a tier of another period re-anchored; an immediate change, its credit and its refusals; a gift's subscription |
| `ImmediateChange`, `StableIdentifier`, `Gift` | The share of the time left and its rounding; what an identifier refuses; a code read as typed, issued, replaced, redeemed once, and every refusal |
| `Subscriptions`, `OfferSellables`, `FeatureAccess` | Orders placed through a real `OrderModifier`, nothing placed for a subscription not due, the sweep, the configured attempts, features of the subscriptions granting access |
| Proration | Through the core's `TaxOrderProcessor`: an upgrade within the period, a second change, a change of period, the day of the renewal, a trial, past due and cancelled refused, the yen, downgrades at renewal and at once with their credit carried over, a failed change, an order that cannot be placed, promotions on the line |
| `Gifts`, `Seats`, organizations | A gift ordered, paid, redeemed and renewed; every refused code; a beneficiary refused for an email address; members of organizations, seats assigned, taken, released and waiting |
| Doctrine adapters | Every query of the four repositories on SQLite in memory, read from a fresh unit of work: orders, limits, the seats counted before a member's |
| Mapping | Every column of the four tables, as an application's migration creates them |
| `PhilCommerceSubscriptionBundle` | The services, the configuration and its bounds, the Doctrine adapters and mapping, the refusal without the core |

A functional suite under `tests/Functional/` boots a real kernel, `tests/Functional/App/TestKernel.php`, with
FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the core and the module: `SubscriptionFlowTest` orders,
pays, sweeps and renews a subscription through the container and reads every step back, `ChangesGiftsAndSeatsTest`
prorates a change before the core taxes it, finds a gift by its code and counts seats in order; the registration test
boots it once without the core to see the container build fail. Symfony wires the event dispatcher of such a kernel only
when an application requires an HTTP kernel: installed on its own, where FrameworkBundle is a development requirement,
the suite is skipped.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-subscription-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `FeatureAccess::grants()` on SQLite in memory, memoised and on
the first check of a request, for a subscriber holding 1, 3 or 10 subscriptions, and for a member reading an
organization's subscriptions and their seat. From the monorepo root:

```bash
composer bench -- packages/commerce-subscription-bundle/tests/Benchmark
```
