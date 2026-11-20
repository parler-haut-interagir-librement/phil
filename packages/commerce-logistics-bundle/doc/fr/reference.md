<!-- phil-dev source-sha256=d00dd196fb4fec837f44bf56640a9add0b89f8c88e70b4ec9ffbd4e85dc62295 -->
<!-- phil-dev source-sha256=0 -->
# Référence

Chaque type public du module.

| Type | Nature | Rôle |
|---|---|---|
| `PhilCommerceLogisticsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_logistics` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Location` | `final class`, entité Doctrine | Un lieu où le stock est tenu, par priorité |
| `Domain\Model\StockLevel` | `final class`, entité Doctrine | Le stock en main et réservé d'un *SKU* à un emplacement |
| `Domain\Model\StockMovement` | `final class`, entité Doctrine | Une ligne du journal du stock |
| `Domain\Model\Reservation` | `final class`, entité Doctrine | Des unités mises de côté pour une commande |
| `Domain\Model\DeliveryMethod` | `final class`, entité Doctrine | Une façon dont une commande parvient à son acheteur |
| `Domain\Model\Shipment` | `final class`, entité Doctrine | Un colis parti : les lignes, le transporteur, le suivi |
| `Domain\Model\ShippingCost` | `final class`, entité Doctrine | Ce qu'une expédition a vraiment coûté : le transporteur, le service, le suivi, le montant |
| `Domain\Model\StockPolicy` | `final class`, entité Doctrine | Si un *SKU* se vend sans stock |
| `Domain\Model\Convention`, `ConventionQuantity` | `final class`, entités Doctrine | Une convention et ses jours ; les unités d'un *SKU* préparées pour elle |
| `Domain\Model\PrintJob` | `final class`, entité Doctrine | Des unités d'une commande envoyées à l'impression, une fois par commande et par *SKU* |
| `Domain\Model\PurchaseOrder` | `final class`, entité Doctrine | Des marchandises commandées à un fournisseur, ce qui est arrivé, quand le reste est attendu |
| `Domain\Model\SerialNumber` | `final class`, entité Doctrine | Une unité connue par son numéro de série, où elle est, la commande avec laquelle elle est partie |
| `Domain\Model\ShippingDocument` | `final class`, entité Doctrine | Le numéro sous lequel un bordereau de livraison ou une liste de prélèvement a été émis |
| `Domain\Model\ShippingWave`, `WaveAssignment` | `final class`, entités Doctrine | Des commandes planifiées pour partir un jour estimé ; une commande dans une vague |
| `Domain\Carrier\Parcel`, `ShippingAddress`, `RateRequest`, `LabelRequest` | `final readonly class` | Ce qu'on demande à un transporteur de tarifer ou d'étiqueter |
| `Domain\Carrier\RateQuote`, `Label` | `final readonly class` | Le tarif d'un transporteur pour un service ; une étiquette, son numéro de suivi, son coût et son document |
| `Domain\Carrier\ShippingMargin` | `final readonly class` | La livraison facturée d'une commande contre le coût de ses colis |
| `Domain\Delivery\FulfilmentState` | énumération adossée à une chaîne | `Unshipped`, `Partial`, `Shipped` |
| `Domain\Stock\MovementKind`, `Domain\Stock\ReservationState`, `Domain\Delivery\DeliveryKind` | énumérations adossées à une chaîne | Pourquoi le stock a bougé, `backordered` compris ; où en est une réservation ; comment une commande est livrée |
| `Domain\Purchasing\PurchaseState`, `Domain\Serial\SerialState`, `Domain\Document\ShippingDocumentKind` | énumérations adossées à une chaîne | Où en est un bon de commande ; où est une unité sérialisée ; un bordereau de livraison ou une liste de prélèvement |
| `Domain\Exception\StockRefused` | `final class`, `DomainError` | Un mouvement, un numéro de série, une vague ou une convention qui ne peut pas être comme demandé |
| `Domain\Exception\PurchaseRefused` | `final class`, `DomainError` | Un bon de commande, ou une réception contre lui, qui ne peut pas avoir lieu |
| `Domain\Exception\CarrierRefused` | `final class`, `DomainError` | Un transporteur inconnu, qui refuse ou ne répond pas ; un coût noté deux fois ou dans une autre devise |
| `Domain\Repository\LocationRepository`, `StockRepository`, `StockPolicyRepository`, `DeliveryMethodRepository`, `ShipmentRepository`, `ShippingCostRepository`, `ConventionRepository`, `PurchaseOrderRepository`, `SerialNumberRepository`, `ShippingDocumentRepository`, `ShippingWaveRepository` | interfaces | Le stockage du module |
| `Application\Port\CarrierGateway` | interface | Les tarifs et les étiquettes d'un transporteur ; étiquette `phil_commerce_logistics.carrier` |
| `Application\Port\PrintOnDemand` | interface | Les unités envoyées à l'impression ; étiquette `phil_commerce_logistics.print_on_demand` |
| `Application\Stock` | `final readonly class` | La réception, les comptages, les transferts, les réservations, leur expédition en entier ou en partie |
| `Application\Shipments` | `final readonly class` | L'expédition d'une commande en colis, ce qui reste, son exécution |
| `Application\DeliveryOptions` | `final readonly class` | Les modes qui desservent une commande, celui qui est choisi, son coût |
| `Application\ShippingProcessor` | `final readonly class`, l'`OrderProcessor` du noyau | Les frais de livraison, après les promotions et avant les taxes |
| `Application\ShippingLabels` | `final readonly class` | Les tarifs des transporteurs, les étiquettes, le coût de chaque expédition, la marge d'une commande |
| `Application\ConventionStock` | `final readonly class` | Les quantités par convention, ce qui reste à imprimer, le chemin d'une commande passée en ligne |
| `Application\Purchasing` | `final readonly class` | Les bons de commande et leurs réceptions dans le stock |
| `Application\SerialNumbers` | `final readonly class` | Les numéros de série enregistrés, déplacés, expédiés et remis en stock |
| `Application\ShippingDocuments` | `final readonly class` | Les bordereaux de livraison et les listes de prélèvement, numérotés par le registre du noyau |
| `Application\Document\DeliveryNote`, `DeliveryNoteLine`, `PickingSlip`, `PickingLine` | `final readonly class` | Ce qu'impriment les deux documents |
| `Application\LogisticsDocumentTypes` | `final readonly class`, les `DocumentTypes` du noyau | Les séries des deux documents |
| `Application\ShippingWaves` | `final readonly class` | Les vagues d'expédition : planifiées, assignées, reportées, libérées |
| `Application\DeferredStock` | `final readonly class`, les `StockCommitments` du noyau | Le stock d'une vente payée plus tard, réservé et libéré |
| `Infrastructure\Doctrine\DoctrineLogisticsRepository` | `final readonly class` | Les onze dépôts sur le gestionnaire d'entités |
| `Infrastructure\CanadaPost\CanadaPostCarrier` | `final class`, `CarrierGateway`, `ResetInterface` | L'API REST de Postes Canada en JSON |
| `Infrastructure\CanadaPost\CanadaPostCredentials` | `final readonly class` | La clé, le secret, le numéro de client, le contrat et les adresses de la boutique |
| `Infrastructure\CanadaPost\CanadaPostPayloads` | `final class` | Les corps JSON envoyés à Postes Canada et relus |

Voyez [scope.md](scope.md) pour ce qui reste hors du module.
