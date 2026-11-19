<!-- phil-dev source-sha256=3c1d80f216f6d031d3d4a844cafe06d64508f831bcf05aa99112aaea69274a9e -->
# Référence

Chaque type public du module.

| Type | Nature | Rôle |
|---|---|---|
| `PhilCommercePromotionsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_promotions` ; refuse de se charger sans `PhilCommerceBundle` et `PhilCommerceCatalogBundle` |
| `Domain\Model\Promotion` | `final class`, entité Doctrine, `Translatable` | Ce que donne une promotion et quand elle s'applique |
| `Domain\Model\PromotionTranslation` | `final class`, entité Doctrine, `Translation` | Le libellé d'une promotion dans une locale |
| `Domain\Promotion\PromotionAction` | énumération adossée à une chaîne | Pourcentage de rabais, montant de rabais, N achetés pour X payés, prix de lot, livraison gratuite |
| `Domain\ValueObject\BundleGroup` | `final readonly class` | Un groupe d'un lot : ses produits et ses unités |
| `Domain\Model\PromotionCode` | `final class`, entité Doctrine | Un code qui débloque une promotion, ses utilisations réclamées jusqu'à sa limite |
| `Domain\Model\PromotionRedemption` | `final class`, entité Doctrine | Une promotion comptée pour une commande passée, avec son code et son client |
| `Domain\Model\PromotionCustomerUse` | `final class`, entité Doctrine | Les utilisations qu'un client a faites d'une promotion |
| `Domain\Contract\PromotionRepository` | interface | Trouve et stocke les promotions, les codes, les utilisations comptées et les utilisations par client ; réclame une utilisation de façon atomique contre les limites |
| `Domain\Exception\PromotionRefused` | `final class`, `DomainError` | Un lot hors des bornes, une commande non passée, une utilisation prise jusqu'à sa limite entre-temps |
| `Application\Contract\CustomerOwnership` | interface | Si un client possède l'un de certains produits, tel que l'application le tient |
| `Application\PromotionEvaluator` | `final readonly class` | Les promotions qu'obtient une commande, et leurs rabais avec leurs origines ; `originOf()` en relit une |
| `Application\PromotionOrder` | `final class` | L'ordre dans lequel les promotions sont essayées : par code, puis par leurs listes `after` |
| `Application\PromotionProcessor` | `final readonly class`, l'`OrderProcessor` du noyau | Les ajustements de promotion, avant `TaxOrderProcessor` |
| `Application\FreeShippingProcessor` | `final readonly class`, l'`OrderProcessor` du noyau | L'annulation des frais de livraison, après le `ShippingProcessor` du module de logistique |
| `Application\Promotions` | `final readonly class` | Les lots de codes ; les utilisations comptées une seule fois par commande passée, à partir de ce qu'elle a obtenu |
| `Application\PromotionTexts` | `final readonly class` | Les libellés dans une locale, à partir des traductions stockées ; écrit une traduction |
| `Application\PromotionTextSet` | `final readonly class` | Les libellés lus pour une liste de promotions |
| `Infrastructure\Doctrine\DoctrinePromotionRepository` | `final readonly class` | Les promotions, les codes, les utilisations comptées et les utilisations par client par le gestionnaire d'entités ; les utilisations réclamées par un `UPDATE` atomique dans une transaction |
