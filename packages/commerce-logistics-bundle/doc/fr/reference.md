<!-- phil-dev source-sha256=0bc2f103351a6b294ca546528e1673b23d1f37194791cb98a4ce1c5edd5c7514 -->
<!-- phil-dev source-sha256=0 -->
# Référence

Chaque type public du module.

| Type | Nature | Rôle |
|---|---|---|
| `PhilCommerceLogisticsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_logistics` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Location` | `final class`, entité Doctrine | Un lieu où le stock est tenu, par priorité |
| `Domain\Model\StockLevel` | `final class`, entité Doctrine | Le stock en main et réservé d'un *SKU* à un emplacement |
| `Domain\Model\StockMovement` | `final class`, entité Doctrine | Une ligne du journal du stock |
| `Domain\Model\Reservation` | `final class`, entité Doctrine | Des unités mises de côté pour une commande ; de celles qui sont parties, combien sont revenues |
| `Domain\Model\DeliveryMethod` | `final class`, entité Doctrine | Une façon dont une commande parvient à son acheteur |
| `Domain\Model\Shipment` | `final class`, entité Doctrine | Un colis parti : les lignes, le transporteur, le suivi |
| `Domain\Model\ShippingCost` | `final class`, entité Doctrine | Ce qu'une expédition a vraiment coûté : le transporteur, le service, le suivi, le montant |
| `Domain\Model\StockPolicy` | `final class`, entité Doctrine | Si un *SKU* se vend sans stock |
| `Domain\Model\Convention`, `ConventionQuantity` | `final class`, entités Doctrine | Une convention et ses jours ; les unités d'un *SKU* préparées pour elle |
| `Domain\Model\PrintJob` | `final class`, entité Doctrine | Des unités d'une commande envoyées à l'impression, une fois par commande et par *SKU* |
| `Domain\Model\PurchaseOrder` | `final class`, entité Doctrine | Des marchandises commandées à un fournisseur, ce qui est arrivé, quand le reste est attendu, le coût unitaire et le propriétaire de chaque ligne |
| `Domain\Purchasing\PurchaseLine` | `final readonly class` | Un *SKU* d'un bon de commande : ses unités, et son coût unitaire et son propriétaire quand ils sont donnés |
| `Domain\Model\CostLayer` | `final class`, entité Doctrine | Les unités d'un *SKU* à un emplacement arrivées ensemble et leur valeur : une réception, un bassin, ou les unités d'un propriétaire sans valeur |
| `Domain\Model\CostEntry` | `final class`, entité Doctrine | Une ligne du journal des coûts : unités, unités sans coût, valeur, propriétaire, motif |
| `Domain\Cost\CostingMethod`, `CostEntryKind`, `VarianceReason` | énumérations à valeur chaîne | `weighted_average` ou `fifo` ; pourquoi une valeur a bougé ; pourquoi les unités en main diffèrent |
| `Domain\Cost\CostShare` | `final class` | Une part d'une valeur à son taux unitaire, arrondie à la hausse à la moitié ; un coût d'approche réparti sur des lignes par `Money::allocate()` |
| `Domain\ValueObject\ValuedStock`, `SaleCost` | `final readonly class` | Les unités avec coût de la boutique pour un *SKU* à un emplacement et leur valeur ; le coût des unités d'une vente pour un *SKU* |
| `Domain\Event\UnitsShipped`, `ShippedLine` | `final readonly class`, la première un `DomainEvent` | Les unités d'une commande qui ont quitté la tablette, par UGS et par emplacement |
| `Domain\Model\SerialNumber` | `final class`, entité Doctrine | Une unité connue par son numéro de série, où elle est, la commande avec laquelle elle est partie |
| `Domain\Model\ShippingDocument` | `final class`, entité Doctrine | Le numéro sous lequel un bordereau de livraison ou une liste de prélèvement a été émis |
| `Domain\Model\ShippingWave`, `WaveAssignment` | `final class`, entités Doctrine | Des commandes planifiées pour partir un jour estimé ; une commande dans une vague |
| `Domain\Carrier\Parcel`, `ShippingAddress`, `RateRequest`, `LabelRequest` | `final readonly class` | Ce qu'on demande à un transporteur de tarifer ou d'étiqueter |
| `Domain\Carrier\RateQuote`, `Label` | `final readonly class` | Le tarif d'un transporteur pour un service ; une étiquette, son numéro de suivi, son coût et son document |
| `Domain\Carrier\ShippingMargin` | `final readonly class` | La livraison facturée d'une commande contre le coût de ses colis |
| `Domain\Delivery\FulfilmentState` | énumération adossée à une chaîne | `Unshipped`, `Partial`, `Shipped` |
| `Domain\Stock\MovementKind`, `Domain\Stock\ReservationState`, `Domain\Delivery\DeliveryKind` | énumérations adossées à une chaîne | Pourquoi le stock a bougé, `adjusted`, `backordered` et `returned` compris ; où en est une réservation ; comment une commande est livrée |
| `Domain\Purchasing\PurchaseState`, `Domain\Serial\SerialState`, `Domain\Document\ShippingDocumentKind` | énumérations adossées à une chaîne | Où en est un bon de commande ; où est une unité sérialisée ; un bordereau de livraison ou une liste de prélèvement |
| `Domain\Exception\StockRefused` | `final class`, `DomainError` | Un mouvement, un numéro de série, une vague ou une convention qui ne peut pas être comme demandé |
| `Domain\Exception\PurchaseRefused` | `final class`, `DomainError` | Un bon de commande, ou une réception contre lui, qui ne peut pas avoir lieu |
| `Domain\Exception\CarrierRefused` | `final class`, `DomainError` | Un transporteur inconnu, qui refuse ou ne répond pas ; un coût noté deux fois ou dans une autre devise |
| `Domain\Exception\CostRefused` | `final class`, `DomainError` | Un coût, une couche, un coût d'approche ou une part de propriétaires qui ne peut pas être enregistré |
| `Domain\Repository\LocationRepository`, `StockRepository`, `StockPolicyRepository`, `DeliveryMethodRepository`, `ShipmentRepository`, `ShippingCostRepository`, `ConventionRepository`, `PurchaseOrderRepository`, `SerialNumberRepository`, `ShippingDocumentRepository`, `ShippingWaveRepository` | interfaces | Le stockage du module |
| `Domain\Contract\CostRepository` | interface | Les couches de coût et le journal des coûts |
| `Application\Contract\InventoryValuation` | interface | La valeur du stock, lue sans rien écrire |
| `Application\Contract\StockOwnership` | interface | À qui appartiennent les unités qui partent d'un niveau que la boutique et des propriétaires se partagent |
| `Application\Port\CarrierGateway` | interface | Les tarifs et les étiquettes d'un transporteur ; étiquette `phil_commerce_logistics.carrier` |
| `Application\Port\PrintOnDemand` | interface | Les unités envoyées à l'impression ; étiquette `phil_commerce_logistics.print_on_demand` |
| `Application\Stock` | `final readonly class` | La réception, les comptages, les ajustements avec un motif, les transferts, les réservations, leur expédition en entier ou en partie, les retours de clients ; annonce `UnitsShipped` |
| `Application\Shipments` | `final readonly class` | L'expédition d'une commande en colis, ce qui reste, son exécution |
| `Application\DeliveryOptions` | `final readonly class` | Les modes qui desservent une commande, celui qui est choisi, son coût |
| `Application\ShippingProcessor` | `final readonly class`, l'`OrderProcessor` du noyau | Les frais de livraison, après les promotions et avant les taxes |
| `Application\ShippingLabels` | `final readonly class` | Les tarifs des transporteurs, les étiquettes, le coût de chaque expédition, la marge d'une commande |
| `Application\ConventionStock` | `final readonly class` | Les quantités par convention, ce qui reste à imprimer, le chemin d'une commande passée en ligne |
| `Application\Purchasing` | `final readonly class` | Les bons de commande et leurs réceptions dans le stock, un coût d'approche réparti sur leurs lignes |
| `Application\Costing` | `final readonly class` | Les couches de coût et le journal tenus au pas de chaque mouvement, par la méthode configurée |
| `Application\InventoryValue` | `final readonly class`, `InventoryValuation` | La valeur du stock d'après les couches de coût et le journal |
| `Application\ShopUnitsFirst` | `final readonly class`, `StockOwnership` | Les unités de la boutique partent d'abord, puis celles de chaque propriétaire dans l'ordre où elles sont arrivées |
| `Application\SerialNumbers` | `final readonly class` | Les numéros de série enregistrés, déplacés, expédiés et remis en stock |
| `Application\ShippingDocuments` | `final readonly class` | Les bordereaux de livraison et les listes de prélèvement, numérotés par le registre du noyau |
| `Application\Document\DeliveryNote`, `DeliveryNoteLine`, `PickingSlip`, `PickingLine` | `final readonly class` | Ce qu'impriment les deux documents |
| `Application\LogisticsDocumentTypes` | `final readonly class`, les `DocumentTypes` du noyau | Les séries des deux documents |
| `Application\ShippingWaves` | `final readonly class` | Les vagues d'expédition : planifiées, assignées, reportées, libérées |
| `Application\DeferredStock` | `final readonly class`, les `StockCommitments` du noyau | Le stock d'une vente payée plus tard, réservé et libéré |
| `Infrastructure\Doctrine\DoctrineLogisticsRepository` | `final readonly class` | Les douze dépôts sur le gestionnaire d'entités |
| `Infrastructure\CanadaPost\CanadaPostCarrier` | `final class`, `CarrierGateway`, `ResetInterface` | L'API REST de Postes Canada en JSON |
| `Infrastructure\CanadaPost\CanadaPostCredentials` | `final readonly class` | La clé, le secret, le numéro de client, le contrat et les adresses de la boutique |
| `Infrastructure\CanadaPost\CanadaPostPayloads` | `final class` | Les corps JSON envoyés à Postes Canada et relus |

Voyez [scope.md](scope.md) pour ce qui reste hors du module.
