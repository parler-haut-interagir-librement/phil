<!-- phil-dev source-sha256=071c7ae3338633bc20333b423e2cbd58213c7bafc01457bdc69ab2d4be664fb5 -->
# Référence

Chaque type public du module.

| Type | Sorte | Rôle |
|---|---|---|
| `PhilCommerceReturnsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_returns` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\ReturnRequest` | `final class`, entité Doctrine | Un retour, ses lignes, ses étapes, les promotions spéciales qu'il brise, la livraison qu'il rend, son étiquette et son numéro de bordereau, sa valeur |
| `Domain\Model\ReturnCreditNote` | `final class`, entité Doctrine | La note de crédit d'un retour |
| `Domain\ValueObject\ReturnLine` | `final readonly class` | Une ligne d'un retour |
| `Domain\ValueObject\ReturnPolicy` | `final readonly class` | Le délai et d'où il compte, les frais de remise en stock, le remboursement de la livraison, l'expiration |
| `Domain\ValueObject\ReturnLabel` | `final readonly class` | L'étiquette d'un transporteur : transporteur, numéro de suivi, fichier |
| `Domain\ValueObject\ReturnSlip` | `final readonly class` | Ce que dit un bordereau de retour |
| `Domain\Event\ReturnStateChanged`, `ReturnSettled`, `ReturnCreditNoteIssued` | `final readonly class`, `DomainEvent` | Une étape, un règlement, une note de crédit |
| `Domain\Rma\ReturnState`, `ReturnReason`, `ItemCondition`, `Resolution`, `DeliveryRefund` | énumérations | Les états, les raisons, les conditions, les règlements, quand la livraison est rendue |
| `Domain\Exception\ReturnRefused` | `final class`, `DomainError` | Un retour refusé, ou une étape qu'il ne peut pas franchir |
| `Domain\Repository\ReturnRepository` | interface | Les retours, les retours autorisés qui attendent depuis un moment, les notes de crédit |
| `Application\Returns` | `final readonly class` | La demande, les étapes, le règlement, l'expiration, les étiquettes, l'anonymisation |
| `Application\ReturnValuation` | `final readonly class` | Ce qu'une demande reprend et ce qu'elle vaut : lignes, ensembles, promotions spéciales, livraison |
| `Application\ReturnCreditNotes` | `final readonly class` | Émet la note de crédit d'un retour |
| `Application\ReturnSlips` | `final readonly class` | Le bordereau d'un retour autorisé, numéroté, et son PDF |
| `Application\ReturnDocumentTypes` | `final readonly class`, le `DocumentTypes` du noyau | La série `return_slip` |
| `Application\Settlement` | `final readonly class` | La façon dont un retour a été réglé |
| `Application\ReturnsGateway` | `final readonly class`, le `PaymentGateway` du noyau | La méthode `exchange` |
| `Application\Port\Restocking` | interface | Remet des unités sur la tablette ; autoconfigurée |
| `Application\Port\StoreCreditGranter` | interface | Accorde un crédit en magasin |
| `Application\Contract\SetParts`, `SpecialDiscounts`, `ShipmentDates` | interfaces | Les parties d'un ensemble, les promotions spéciales, la dernière expédition d'une commande |
| `Application\Contract\ReturnLabels`, `ReturnSlipRenderer` | interfaces | L'étiquette d'un transporteur, un bordereau en PDF |
| `Infrastructure\Doctrine\DoctrineReturnRepository` | `final readonly class` | Le dépôt sur le gestionnaire d'entités |
| `Infrastructure\StoredValue\StoredValueStoreCredit` | `final readonly class` | Le crédit en magasin par `phil/commerce-stored-value-bundle` |
| `Infrastructure\Catalog\CatalogSetParts` | `final readonly class` | Les ensembles par `phil/commerce-catalog-bundle` |
| `Infrastructure\Promotions\PromotionSpecials` | `final readonly class` | Les promotions spéciales par `phil/commerce-promotions-bundle` |
| `Infrastructure\Logistics\LogisticsShipmentDates` | `final readonly class` | Les dates d'expédition par `phil/commerce-logistics-bundle` |
| `Infrastructure\Logistics\LogisticsRestocking` | `final readonly class`, `Restocking` | Les unités revendables remises dans le stock de `phil/commerce-logistics-bundle`, au coût auquel elles sont parties |
| `Infrastructure\PagedMedia\PagedMediaReturnSlips` | `final readonly class` | Les bordereaux produits avec `phil/paged-media` |

Voir [scope.md](scope.md) pour ce qui n'est pas commencé.
