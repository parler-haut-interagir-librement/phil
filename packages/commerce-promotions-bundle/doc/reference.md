# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommercePromotionsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_promotions`; refuses to load without `PhilCommerceBundle` and `PhilCommerceCatalogBundle` |
| `Domain\Model\Promotion` | `final class`, Doctrine entity, `Translatable` | What a promotion gives and when it applies |
| `Domain\Model\PromotionTranslation` | `final class`, Doctrine entity, `Translation` | A promotion's label in one locale |
| `Domain\Promotion\PromotionAction` | string-backed enum | Percent off, amount off, buy N pay X, bundle price, free shipping |
| `Domain\ValueObject\BundleGroup` | `final readonly class` | A group of a bundle: its products and its units |
| `Domain\Model\PromotionCode` | `final class`, Doctrine entity | A code unlocking a promotion, its uses claimed up to its limit |
| `Domain\Model\PromotionRedemption` | `final class`, Doctrine entity | One promotion counted for one placed order, with its code and its customer |
| `Domain\Model\PromotionCustomerUse` | `final class`, Doctrine entity | The uses one customer made of one promotion |
| `Domain\Contract\PromotionRepository` | interface | Finds and stores promotions, codes, redemptions and uses per customer; claims a use atomically against the limits |
| `Domain\Exception\PromotionRefused` | `final class`, `DomainError` | A batch out of bounds, an order not placed, a use taken to its limit meanwhile |
| `Application\Contract\CustomerOwnership` | interface | Whether a customer owns one of some products, as the application keeps it |
| `Application\PromotionEvaluator` | `final readonly class` | Which promotions an order gets, and their discounts with their origins; `originOf()` reads one back |
| `Application\PromotionOrder` | `final class` | The order promotions are tried in: by code, then by their `after` lists |
| `Application\PromotionProcessor` | `final readonly class`, the core's `OrderProcessor` | The promotion adjustments, before `TaxOrderProcessor` |
| `Application\FreeShippingProcessor` | `final readonly class`, the core's `OrderProcessor` | The waiver of the delivery charge, after the logistics module's `ShippingProcessor` |
| `Application\Promotions` | `final readonly class` | Batches of codes; uses counted once per placed order, from what it got |
| `Application\PromotionTexts` | `final readonly class` | Labels in a locale, from stored translations; writes a translation |
| `Application\PromotionTextSet` | `final readonly class` | The labels read for a list of promotions |
| `Infrastructure\Doctrine\DoctrinePromotionRepository` | `final readonly class` | Promotions, codes, redemptions and uses per customer through the entity manager; uses claimed by an atomic `UPDATE` inside a transaction |
