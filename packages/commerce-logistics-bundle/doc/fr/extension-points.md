<!-- phil-dev source-sha256=30353058e2488297edcf1ad31e8eb235ec6ba89d62ce57789e4d84b7eb6eacf8 -->
<!-- phil-dev source-sha256=0 -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application étend ou remplace. Les ports du noyau
et leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette et ordre |
|---|---|---|
| `OrderProcessor` | `ShippingProcessor`, les frais de livraison | `phil_commerce.order_processor`, après les promotions et avant les taxes du noyau |
| `StockCommitments` | `DeferredStock`, le stock d'une vente payée plus tard | `phil_commerce.stock_commitments` |
| `DocumentTypes` | `LogisticsDocumentTypes`, le bordereau de livraison et la liste de prélèvement | `phil_commerce.document_types` |

Un processeur propre à l'application nomme `ShippingProcessor` dans le `before` ou le `after` de son étiquette. Voyez
[Portée](scope.md).

## Les transporteurs

`Application\Port\CarrierGateway` est le port du module pour les tarifs et les étiquettes d'un transporteur. Une
application ajoute un transporteur par un service qui l'implémente, que l'autoconfiguration étiquette
`phil_commerce_logistics.carrier` ; `ShippingLabels` interroge chaque transporteur étiqueté. `CanadaPostCarrier` est
enregistré quand `phil_commerce_logistics.carriers.canada_post` est activé — voyez [Transporteurs](carriers.md).

| Configuration | Ce qu'elle règle |
|---|---|
| `carriers.canada_post.enabled` | Enregistre l'adaptateur de Postes Canada ; désactivé par défaut |
| `carriers.canada_post.api_key`, `api_secret` | La clé et le secret de l'application de développeur, tirés de l'environnement ou du coffre des secrets |
| `carriers.canada_post.customer_number`, `contract_id` | Le client facturé ; le contrat qui donne les tarifs commerciaux |
| `carriers.canada_post.base_uri`, `token_uri` | La passerelle de l'API et l'adresse de ses jetons d'accès, en HTTPS seulement |
| `carriers.canada_post.language` | `en-CA` ou `fr-CA`, la langue des messages du transporteur |

## L'impression à la demande

`Application\Port\PrintOnDemand` reçoit les articles de convention d'une commande passée en ligne qui vont à
l'impression. Un service qui l'implémente reçoit l'étiquette `phil_commerce_logistics.print_on_demand` par
autoconfiguration ; `ConventionStock` les appelle tous, une fois par commande et par *SKU* — voyez
[Conventions](conventions.md).

| Configuration | Ce qu'elle règle |
|---|---|
| `documents.delivery_note_prefix`, `picking_slip_prefix`, `digits` | Les séries des deux documents dans le registre du noyau ; `DN-`, `PS-` et 8 par défaut |
| `conventions.window_days` | Combien de jours à l'avance une convention compte comme proche, de 0 à 366 ; 30 par défaut |
| `conventions.online_channels` | Les canaux dont les commandes sont passées en ligne, avec les commandes sans canal |

## `ShipmentAuthorization`

`Application\Contract\ShipmentAuthorization` dit si une commande passée peut partir maintenant. Un service qui
l'implémente reçoit l'étiquette `phil_commerce_logistics.shipment_authorization` par autoconfiguration ;
`Shipments::ship()` les interroge par `AnyShipmentAuthorization` et expédie quand une répond oui.
`PaidShipmentAuthorization`, celle du bundle, répond oui une fois que les paiements couvrent ce que la commande doit
— voyez [Expéditions](shipments.md#expédition-autorisée).

| Méthode | Contrat |
|---|---|
| `authorizes(Order $order): bool` | Si la commande peut partir ; elle ne change, ne facture ni n'enregistre rien |

```php
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentAuthorization;

/** The customers the application invoices at the end of the month, whose orders leave before they are paid. */
final readonly class InvoicedCustomers implements ShipmentAuthorization
{
    /** @param list<string> $customers stable identifiers */
    public function __construct(private array $customers)
    {
    }

    public function authorizes(Order $order): bool
    {
        return \in_array($order->customer(), $this->customers, true);
    }
}
```

## `ShipmentForcing`

`Application\Contract\ShipmentForcing` dit qui peut expédier, par `Shipments::force()`, une commande qu'aucune
autorisation ne laisse partir. `DenyShipmentForcing`, l'alias du bundle, refuse tout le monde — voyez
[Expéditions](shipments.md#forcer-une-expédition).

| Méthode | Contrat |
|---|---|
| `allows(Order $order, string $actor): bool` | Si l'auteur, par son identifiant stable, peut forcer l'expédition de la commande |

```php
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentForcing;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class VoterShipmentForcing implements ShipmentForcing
{
    public function __construct(private Security $security)
    {
    }

    public function allows(Order $order, string $actor): bool
    {
        return $this->security->isGranted('ROLE_SHIPMENT_FORCE', $order);
    }
}
```

```yaml
services:
    Phil\CommerceLogisticsBundle\Application\Contract\ShipmentForcing: '@App\Logistics\VoterShipmentForcing'
```

## Le calcul des coûts

`Application\Contract\StockOwnership` dit à qui appartiennent les unités qui partent d'un niveau de stock que se partagent
les unités de la boutique et des unités laissées en consignation. Avec le calcul des coûts activé, son alias pointe vers
`ShopUnitsFirst`, les unités de la boutique d'abord ; une application, ou un module de consignation, le fait pointer
vers un de ses propres services. `Application\Contract\InventoryValuation` est ce qu'une application, un rapport ou un
catalogue lit de la valeur du stock : un alias public de `InventoryValue`, enregistré avec ou sans calcul des coûts.
Voyez [Coûts](costing.md).

| Configuration | Ce qu'elle règle |
|---|---|
| `costing.enabled` | Évalue le stock au coût ; désactivé par défaut |
| `costing.method` | `weighted_average`, la valeur par défaut, ou `fifo` |
| `costing.currency` | Le code ISO 4217 dans lequel chaque coût est donné, obligatoire une fois le calcul des coûts activé |

## Les événements

| Événement | Envoyé par | Quand |
|---|---|---|
| `Domain\Event\UnitsShipped` | `Stock`, par le service `event_dispatcher` quand il y en a un | Des unités ont quitté la tablette : une fois par `ship()` ou `shipPart()` qui a déplacé des unités, une ligne par *SKU* et par emplacement |
| `Domain\Event\ShipmentForced` | `Shipments`, par le service `event_dispatcher` quand il y en a un | Quelqu'un a forcé une expédition qu'aucune autorisation ne laissait partir, une fois qu'elle est gardée |

Les événements ne portent que des identifiants et des quantités — voyez [Expéditions](shipments.md#les-unités-expédiées).

## Les dépôts

Chaque port de dépôt est un alias public de l'adaptateur Doctrine du module ; une application le fait pointer vers un
de ses propres services sous le même contrat. `DoctrineLogisticsRepository` les implémente tous.

| Port | Ce que garde un stockage propre à l'application |
|---|---|
| `StockRepository` | Chaque mouvement dans le journal, `levelsOfSkus()` lisant plusieurs *SKU* d'un coup |
| `LocationRepository` | Les emplacements, et ceux qui sont ouverts |
| `DeliveryMethodRepository` | Les modes de livraison, et ceux qui sont actifs |
| `ShipmentRepository` | Les expéditions d'une commande |
| `ShippingCostRepository` | Un coût par expédition, et les coûts d'une commande |
| `StockPolicyRepository` | Si chaque *SKU* se vend sans stock |
| `ConventionRepository` | Les conventions, les quantités préparées pour elles, `conventionsMeeting()` et `conventionSkus()` en une requête chacune, et les unités envoyées à l'impression |
| `PurchaseOrderRepository` | Les bons de commande, et ceux qui sont ouverts |
| `SerialNumberRepository` | Les unités sérialisées, combien sont en stock à un emplacement, celles d'une commande |
| `ShippingDocumentRepository` | Les numéros des bordereaux de livraison et des listes de prélèvement |
| `ShippingWaveRepository` | Les vagues d'expédition, celles qui sont planifiées, et les commandes de chacune |
| `Domain\Contract\CostRepository` | Les couches de coût, avec celles données et pas encore vidées, et le journal des coûts, additionnés par *SKU* et par emplacement ou par vente, et paginés |

## Les services

| Service | Comment une application l'utilise |
|---|---|
| `Stock` | Reçoit, compte, ajuste, transfère, réserve, expédie et reprend les retours de clients ; public — voyez [Stock](stock.md) |
| `Shipments` | Expédie en un colis ou en plusieurs, avec le transporteur et le suivi ; public — voyez [Expéditions](shipments.md) |
| `DeliveryOptions` | Les modes de livraison d'une zone, leurs prix et leurs jours estimés ; public — voyez [Livraison](delivery.md) |
| `ShippingLabels` | Les tarifs et les étiquettes des transporteurs, le coût de chaque expédition, la marge d'une commande ; public — voyez [Transporteurs](carriers.md) |
| `ConventionStock` | Les quantités préparées par convention, ce qui reste à imprimer, le chemin d'une commande passée en ligne ; public — voyez [Conventions](conventions.md) |
| `Purchasing` | Les bons de commande, le coût unitaire et le propriétaire de leurs lignes, et leurs réceptions ; public — voyez [Achats](purchasing.md) |
| `SerialNumbers` | Le numéro de série de chaque unité ; public — voyez [Achats](purchasing.md) |
| `ShippingDocuments` | Les bordereaux de livraison et les listes de prélèvement ; public — voyez [Expéditions](shipments.md) |
| `ShippingWaves` | Les commandes planifiées pour partir ensemble un jour estimé ; public — voyez [Expéditions](shipments.md) |
| `InventoryValuation` | Le coût unitaire d'un *SKU*, le stock au coût, le coût des ventes, le journal des coûts ; public — voyez [Coûts](costing.md) |

Avec `phil/commerce-returns-bundle`, sa remise en stock de la logistique, ou un `Restocking` propre à l'application, remet en stock les unités retournées par
`Stock::returnFromCustomer()`, au coût auquel elles sont parties quand le calcul des coûts est activé.
