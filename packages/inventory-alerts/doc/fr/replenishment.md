<!-- phil-dev source-sha256=8fe10d0a68208d2f0c3e150d22eefaa2daec678a23f220711e8af0ef744cc9ac -->
# Réapprovisionnement

Toute quantité est en unités entières. Une quantité calculée est arrondie à l'entier supérieur — un stock de sécurité
de 49,3 unités vaut 50 —, une fois écartées les erreurs d'arrondi de l'arithmétique à virgule flottante : 0,1 × 30
fait 3 unités, non 4. La demande est quotidienne et le délai d'approvisionnement en jours, à moins que l'application
ne garde une autre période pour les deux.

## Historique de la demande

`DemandHistory` contient la demande de chaque période, de la plus ancienne à la plus récente, de 1 à 3 660 périodes,
chacune de 0 ou plus.

```php
use Phil\InventoryAlerts\Replenishment\DemandHistory;

$history = new DemandHistory([4, 6, 5, 7, 3]);
$history->mean();              // 5.0
$history->standardDeviation(); // 1.58: the sample deviation, √(10 ÷ 4)
$history->max();               // 7.0
```

## Stock de sécurité

La **méthode simple** garde ce que le pire cas consommerait au-delà du cas moyen, jamais moins de 0 :

```text
safety stock = max daily demand × max lead time − average daily demand × average lead time
```

```php
use Phil\InventoryAlerts\Replenishment\SafetyStock;

SafetyStock::simple(maxDailyDemand: 20, maxLeadTimeDays: 10, averageDailyDemand: 12, averageLeadTimeDays: 7); // 116
```

La **méthode statistique** garde assez pour un niveau de service — la part des cycles de réapprovisionnement qui se
terminent sans rupture :

```text
safety stock = z × √(lead time × σ(demand)² + average demand² × σ(lead time)²)
```

`z` est le quantile de la loi normale pour le niveau de service : 1,2816 pour 90 %, 1,6449 pour 95 %, 2,3263 pour
99 %. Quand le délai ne varie pas, la formule devient z × σ(demande) × √(délai).

```php
use Phil\InventoryAlerts\Replenishment\ServiceLevel;

$level = ServiceLevel::of(0.95);   // from 0.5 to 0.9999
$level->z();                       // 1.6448536

SafetyStock::statistical($level, demandStandardDeviation: 10, leadTimeDays: 9);                                                 // 50
SafetyStock::statistical($level, demandStandardDeviation: 10, leadTimeDays: 9, averageDailyDemand: 20, leadTimeStandardDeviation: 2); // 83
SafetyStock::fromHistory($history, $level, leadTimeDays: 4);                                                                   // 6
```

## Point de commande

```text
reorder point = average daily demand × lead time + safety stock
```

```php
use Phil\InventoryAlerts\Replenishment\ReorderPoint;

ReorderPoint::of(averageDailyDemand: 12, leadTimeDays: 7, safetyStock: 116); // 200
ReorderPoint::fromHistory($history, leadTimeDays: 4, serviceLevel: ServiceLevel::of(0.95)); // 26
```

## Quantité économique de commande

```text
EOQ = √(2 × annual demand × cost of an order ÷ annual cost of holding a unit)
```

Les deux coûts sont des données que fournit l'application, dans la même devise ; la bibliothèque ne garde aucun coût
et ne renvoie que des unités.

```php
use Phil\InventoryAlerts\Replenishment\EconomicOrderQuantity;

$eoq = new EconomicOrderQuantity(annualDemand: 1_200, orderCost: 50, annualHoldingCost: 3);
$eoq->exact();     // 200.0
$eoq->quantity(12); // 204: rounded up to whole packs of 12
```

## Règles

Une `ReplenishmentRule` dit comment une UGS (unité de gestion de stock) est réapprovisionnée à un emplacement, et par
quel fournisseur. Sa quantité est toujours arrondie à la taille du lot.

- **Min/max** — `ReplenishmentRule::minMax($sku, $location, $supplier, $minimum, $maximum, $packSize)` : au minimum ou
  en dessous, commander jusqu'au maximum.
- **Quantité fixe** — `ReplenishmentRule::fixedQuantity($sku, $location, $supplier, $reorderPoint, $orderQuantity,
  $packSize)` : au point de commande ou en dessous, commander la quantité — telle que la quantité économique —, autant
  de fois qu'il faut pour remonter au-dessus du point de commande.

La règle décide d'après la **position de stock** : en main, moins réservé, plus en commande. Les unités déjà commandées
ne sont pas commandées de nouveau.

## Propositions d'achat

`ReplenishmentPlanner::propose()` lit le niveau de l'UGS et de l'emplacement de chaque règle, et renvoie une
`PurchaseProposal` par fournisseur, dans l'ordre de la première règle de chaque fournisseur, ses lignes triées par
emplacement puis par UGS. Un emplacement qui n'a jamais contenu l'UGS a une position de 0. Au plus 100 000 règles sont
lues par appel, une par UGS et par emplacement.

```php
use Phil\InventoryAlerts\Replenishment\ReplenishmentPlanner;

foreach (new ReplenishmentPlanner($stockSource)->propose($rules, $now) as $proposal) {
    // $proposal->supplier, $proposal->lines (sku, location, quantity, position, reorderPoint), $proposal->byLocation()
}
```

Une proposition attend une personne. Avant de décider, la personne peut changer la quantité d'une ligne ; une quantité
de 0 la retire, et la dernière ligne n'est pas retirée — la proposition est plutôt rejetée.

```php
$proposal = $proposal->withQuantity('MUG-BLUE', 'store', 48);
$approved = $proposal->approve('user-42', $now);   // or ->reject('user-42', $now)
```

Une proposition tranchée ne peut plus être modifiée ni tranchée de nouveau (`ProposalAlreadyDecided`). La bibliothèque
ne passe jamais de commande : l'application garde les propositions et remet une proposition approuvée à ses achats.
