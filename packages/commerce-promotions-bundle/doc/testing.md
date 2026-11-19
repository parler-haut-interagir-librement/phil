# Testing

## How the module is tested

| Area | What is covered |
|---|---|
| `Promotion`, `PromotionCode`, `BundleGroup` | Each action and its bounds, every condition and refusal, bundles and their groups, free shipping and its methods, exclusions, incompatibilities both ways, uses claimed up to the limits and released, labels and their translations |
| `PromotionRedemption`, `PromotionCustomerUse`, `PromotionRefused` | Their bounds, a customer's uses up to the limit, every message |
| `PromotionEvaluator`, `PromotionProcessor`, `PromotionOrder` | Each action through a real core with its taxes after the discounts; bundles filled from the cheapest lines, several bundles, a bundle after a share off, minimums; the order set by `after`, a loop set aside; exclusivity and incompatibility; uses per customer and owned products; codes matched whatever their case and looked up once; origins of the discounts; products only |
| `FreeShippingProcessor` | The waiver of the delivery charge under the promotion's label and origin, per method and minimum, the first free shipping only, neutral charges left, running twice changing nothing |
| `Promotions` | Batches of codes; uses counted once per order from what it got, for the customer too; a use taken meanwhile refusing the placement |
| `PromotionTexts` | Labels read along a fallback chain from stored translations, a translation written in its own locale, discounts labelled in the buyer's locale, labels without translations |
| Mapping | Every column of the five tables, as an application's migration creates them |
| `PhilCommercePromotionsBundle` | The processors and their order, the services and their arguments, translations configured or not, the Doctrine adapter and mapping, the refusal without the core or the catalogue |

A functional suite under `tests/Functional/` boots a real kernel with FrameworkBundle, DoctrineBundle on an
in-memory SQLite database, the core, the catalogue and the module: `PromotionsFlowTest` discounts an order once by a
code, counted in the transaction that places it and once only, gives the last use of a single-use code to one order
of two, claims a customer's uses under the limit, stores and reads back a bundle and its translation, and refuses to
claim a use outside a transaction. Installed on its own, where FrameworkBundle is a development requirement, the
suite is skipped.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/commerce-promotions-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

In the monorepo, Infection holds the module at 100 % of covered mutants.

## Benchmarks

The phpbench benchmark in `tests/Benchmark/` measures `PromotionEvaluator::evaluate()` on carts of 5 to 100 lines
against 3 to 30 promotions, a bundle every fourth one, each ordered after the one before. From the monorepo root:

```bash
composer bench -- packages/commerce-promotions-bundle/tests/Benchmark
```
