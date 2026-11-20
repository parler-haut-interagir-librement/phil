# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application extends or replaces. The ports of the
core and their contracts are described there. The package is at `0.x`: its extension points are stable only from its
1.0 release; until then, a minor version may change them, and its CHANGELOG says how.

## The core's ports it implements

| Port | Service | Tag and order |
|---|---|---|
| `OrderProcessor` | `PromotionProcessor` | `phil_commerce.order_processor`, before the core's `TaxOrderProcessor` and `ManualDiscountProcessor` and the logistics module's `ShippingProcessor` |
| `OrderProcessor` | `FreeShippingProcessor` | `phil_commerce.order_processor`, after `PromotionProcessor` and the logistics module's `ShippingProcessor`, before the core's taxes |

The logistics module's processor is named by its class, as a string: the constraint is ignored when the module is not
installed. An application's own processor names these services in its tag's `before` or `after` to take its place
in the order: a discount of its own before or after the promotions.

## What an application implements

| Port | When |
|---|---|
| `Application\Contract\CustomerOwnership` | To run promotions for the owners of a product (`ownedSkus`): `ownsAny(customer, skus)` says whether the customer bought, received or unlocked one of them. Without a service, such a promotion never applies. |

## Repository

`Domain\Contract\PromotionRepository` is a public alias of `DoctrinePromotionRepository`; an application points it
at a service of its own under the same contract. `claimUse()` and `claimCustomerUse()` stay atomic against their
limits, so a code or a customer limited in uses never gets one use too many.

## Services

| Service | How an application relies on it |
|---|---|
| `Promotions` | Creates batches of codes, and counts the uses of a placed order; public |
| `PromotionTexts` | Reads labels in a locale and writes their translations; public. See [Configuration](configuration.md) |
| `PromotionEvaluator` | What an order gets, for a preview; `originOf()` reads the promotion and code back from a discount |
