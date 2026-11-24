<!-- phil-dev source-sha256=bc518938f74d8dc444b9bb302016d10b7d4a80c5bef570f7c1c7667fefdd54d5 -->
# Coûts

Avec le calcul des coûts activé, le module tient ce que vaut au coût le stock de la boutique à mesure qu'il bouge :
chaque réception, expédition, comptage, ajustement, transfert et retour de client que `Stock` et `Purchasing` écrivent
dans le journal du stock déplace avec lui la valeur du stock, dans des couches de coût et un journal des coûts à part.
Des rapports, un catalogue qui calcule le coût de ses produits composés, ou une vérification de marge le lisent par
`Contract\InventoryValuation`, sans rien écrire.

Le calcul des coûts est désactivé par défaut : le module ne stocke alors aucune couche de coût ni aucune entrée de coût,
et chaque réponse de `InventoryValuation` est vide.

```yaml
# config/packages/phil_commerce_logistics.yaml
phil_commerce_logistics:
    costing:
        method: weighted_average   # or fifo: first in, first out
        currency: CAD              # the ISO 4217 code every cost is given in
```

| Configuration | Ce qu'elle règle |
|---|---|
| `costing.enabled` | Active le calcul des coûts ; donner une autre clé de `costing` l'active aussi |
| `costing.method` | `weighted_average`, la valeur par défaut, ou `fifo` |
| `costing.currency` | La devise dans laquelle le stock est évalué, trois lettres majuscules ; obligatoire une fois le calcul des coûts activé |

## Les méthodes

La méthode évalue les unités propres de la boutique par *SKU* et par emplacement.

| Méthode | Comment les unités partent |
|---|---|
| `weighted_average` | Un bassin par *SKU* et par emplacement : sa valeur sur ses unités. Une unité part à la moyenne du bassin, et chaque réception change la moyenne. |
| `fifo` | Une couche par réception, par *SKU* et par emplacement : les unités partent de la couche la plus ancienne d'abord, au coût de cette couche. |

```php
$purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(3, Money::ofDecimal('10.00', $cad))], $clock->now());
$purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(2, Money::ofDecimal('12.50', $cad))], $clock->now());
$purchasing->receive('PO-1', ['DICE' => 3], $clock->now());
$purchasing->receive('PO-2', ['DICE' => 2], $clock->now());

$valuation->unitCost('DICE');   // 11.00 CAD with a weighted average; 11.00 too with first in, first out, over its layers
// Four dice ship: 44.00 CAD with a weighted average, 30.00 + 12.50 = 42.50 CAD first in, first out.
```

- **Des entiers seulement.** Chaque montant est un `Money` en unités mineures ; une part d'une valeur est répartie par
  `Money::allocate()`, si bien que les unités qui quittent une couche une à une prennent exactement ce que la couche
  contenait, à l'unité mineure près, la dernière prenant ce qui reste. Un coût unitaire lu dans un bassin est arrondi à
  la hausse à la moitié.
- **Un coût unitaire vient de la ligne du bon de commande** — `PurchaseLine::of(units, unitCost)`, voyez
  [Achats](purchasing.md) — ou de `Stock::receive(..., unitCost: $cost)` pour une réception hors d'un bon de commande.
  Il est de zéro ou plus, dans la devise configurée, et refusé avec `CostRefused` sinon.
- **Les unités reçues sans coût unitaire, ou avant que le calcul des coûts soit activé, sont sans coût** : elles ne
  déplacent aucune valeur, et le journal des coûts les compte à part, dans `uncosted`. Les unités partent d'abord des
  couches avec coût.
- **Les unités trouvées par un comptage ou un ajustement** entrent au coût actuel du niveau : la moyenne du bassin, ou
  celle de la couche la plus récente en premier entré, premier sorti ; sans coût quand le niveau ne contient aucune
  unité avec coût.
- **Un transfert emporte le coût et le propriétaire.** En premier entré, premier sorti, chaque couche garde le moment où
  elle a été reçue, si bien que les unités gardent leur place dans la file au nouvel emplacement ; en coût moyen pondéré,
  elles rejoignent le bassin de la cible.
- **Changer de méthode** laisse les couches telles quelles : un coût moyen pondéré fusionne en un seul bassin, la
  première fois qu'il les déplace, les couches que le premier entré, premier sorti a laissées à un *SKU* et un
  emplacement.

## Les coûts d'approche

Le fret, les droits et le courtage d'une réception sont répartis sur ses lignes :

```php
$purchasing->receive('PO-1', ['BOOK' => 3, 'ZINE' => 1], $clock->now(), landedCost: Money::ofDecimal('1.00', $cad));
// BOOK: 3 × 10.00 = 30.00 and ZINE: 5.00 bear 1.00 in proportion: 0.86 and 0.14.
```

- Le coût d'approche est réparti sur les lignes de cette réception dont les unités sont celles de la boutique et ont un
  coût unitaire, en proportion de leur valeur — unités × coût unitaire — par `Money::allocate()` : les parts s'additionnent
  au coût d'approche à l'unité mineure près, les unités mineures qui restent allant aux plus grands restes. Quand toutes
  les lignes porteuses ne coûtent rien, les parts suivent leurs unités.
- Chaque part est ajoutée à la couche de cette réception en premier entré, premier sorti, ou au bassin en coût moyen
  pondéré, et journalisée comme `landed`.
- Un coût d'approche exige le calcul des coûts (`CostRefused::notCosted()`), une ligne qui le porte
  (`CostRefused::noLineBears()`) et la devise configurée ; il est de zéro ou plus.

## Les écarts

Un comptage ou un ajustement qui trouve des unités manquantes ou en trop peut en dire la raison, par un
`Domain\Cost\VarianceReason` :

```php
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;

$stock->adjustFor('DICE', 'warehouse', -1, VarianceReason::Theft, $clock->now(), 'COUNT-12');
$stock->count('DICE', 'store', 18, $clock->now(), 'COUNT-13', VarianceReason::CountError);
```

| Motif | Valeur | Quand |
|---|---|---|
| `Damaged` | `damaged` | Brisé ou gâté sur la tablette, dans la réserve ou à la manutention |
| `Theft` | `theft` | Volé |
| `Lost` | `lost` | Disparu sans cause connue |
| `Expired` | `expired` | Périmé, plus vendu |
| `CountError` | `count_error` | Un comptage, une réception ou une expédition antérieurs mal comptés |
| `SupplierError` | `supplier_error` | Le fournisseur a envoyé moins d'unités, plus d'unités ou d'autres unités que ses papiers ne le disent |
| `InternalUse` | `internal_use` | Pris pour l'usage de la boutique : une démonstration, un échantillon, un présentoir |
| `Donated` | `donated` | Donné : un don, un prix |

- Le motif va au journal du stock, dans `StockMovement::$reason`, avec ou sans calcul des coûts ; `adjust()` et un
  `count()` sans motif le laissent nul.
- Avec le calcul des coûts, le journal des coûts garde l'écart comme `variance`, avec son motif et la valeur des unités
  au coût : ce que lit un rapport de démarque, par `InventoryValuation::entries()`.

## Les retours de clients

`Stock::returnFromCustomer()` remet les unités au coût auquel elles sont parties, jamais au coût du jour : leur part de
ce qu'ont coûté les sorties de ce *SKU* pour la commande, moins ce que les retours précédents ont repris, par
`Money::allocate()`. Cela fonctionne de la même façon sous les deux méthodes : en premier entré, premier sorti, les
unités reviennent comme une couche à part, reçue au moment du retour.

- Les unités dont le journal des coûts n'a jamais vu la sortie — une vente expédiée avant que le calcul des coûts soit
  activé — reviennent sans coût.
- Les unités d'un propriétaire reviennent à ce propriétaire, dans la mesure où les sorties de la commande le disent ;
  voyez ci-dessous.
- `phil/commerce-returns-bundle` remet en stock par `returnFromCustomer()` quand sa remise en stock de la logistique est
  activée.

## Les propriétaires et la consignation

Les unités reçues avec un propriétaire — `PurchaseLine::of(units, unitCost, owner)`, ou
`Stock::receive(..., owner: 'consignor-17')` — sont laissées en consignation : elles vont dans une couche de ce
propriétaire, qui contient des unités et aucune valeur. Elles n'entrent jamais dans les couches de la boutique ni dans
sa valeur, quoi que dise leur bon de commande sur leur coût, et aucun coût d'approche ne les atteint.

- **Quand des unités partent d'un niveau qui contient des unités de la boutique et d'un propriétaire**,
  `Contract\StockOwnership` dit lesquelles partent. `ShopUnitsFirst` est la réponse du module : les unités propres de la
  boutique d'abord, puis celles de chaque propriétaire, le propriétaire dont les unités sont arrivées en premier d'abord.
  Une application, ou un module de consignation, fait pointer l'alias de `StockOwnership` vers un de ses propres
  services.
- `StockOwnership::leaving()` reçoit les unités qui partent, les unités de la boutique au niveau, chaque propriétaire et
  ses unités, et pourquoi elles partent ; elle répond pour chaque propriétaire combien de ses unités partent. Une réponse
  qui donne à un propriétaire plus qu'il n'en détient, ou qui fait partir de la boutique plus d'unités qu'elle n'en
  détient, est refusée avec `CostRefused::share()`.
- Chaque mouvement des unités d'un propriétaire est journalisé avec le propriétaire, sa valeur toujours nulle :
  `InventoryValuation` ne les compte jamais.

## Lire la valeur

`Contract\InventoryValuation`, un alias public de `InventoryValue`, est ce que lit une application. Appeler `Costing`
lui-même compterait un mouvement deux fois.

| Méthode | Ce qu'elle donne |
|---|---|
| `unitCost(sku)` | Ce que coûte maintenant une unité à la boutique : la valeur de ses unités avec coût sur leur nombre, à chaque emplacement, arrondie à la hausse à la moitié ; sans unité en main, le coût unitaire de sa dernière réception à un coût connu ; null s'il n'en a jamais eu |
| `valuedStock(at)` | `ValuedStock` par *SKU* et par emplacement : les unités avec coût de la boutique et leur valeur à un moment, par *SKU* puis par emplacement |
| `costOfSales(from, until)` | `SaleCost` par vente et par *SKU* : les unités que ses sorties ont prises moins celles revenues dans la période, les unités sans coût parmi elles, et leur coût |
| `entries(from, until, kinds, after, limit)` | Le journal des coûts d'une période, de certains genres ou de tous, dans l'ordre où il a été écrit, de 1 à 1 000 entrées par page après l'identifiant de la dernière entrée de la page précédente |

Une période va de son début, inclus, à sa fin, exclue ; les moments sont comparés en UTC.

## Stockage

| Table | Contenu |
|---|---|
| `phil_commerce_logistics_cost_layer` | `id` (UUID v7), `sku`, `location`, `owner` (nul pour la boutique), `units`, `value` (unités mineures), `currency`, `received_at`, `reference` ; indexée par *SKU* et emplacement. Une couche vidée est supprimée. |
| `phil_commerce_logistics_cost_entry` | `id` (UUID v7), `sku`, `location`, `kind`, `owner`, `units`, `uncosted`, `value` (unités mineures, signée), `currency`, `reason`, `reference`, `moved_at` ; indexée par *SKU*, emplacement et moment, par référence, et par moment. Jamais modifiée, jamais supprimée. |

La colonne `reason` de `phil_commerce_logistics_stock_movement` est nullable. Une base de 0.1.0-alpha gagne les deux
tables et cette colonne, et rien d'autre ne change ; voyez [Stock](stock.md#stockage).

Les couches et les entrées de coût données à `DoctrineLogisticsRepository` et pas encore vidées sont trouvées comme si
elles étaient stockées, et une couche retirée et pas encore vidée n'est plus trouvée : deux réceptions dans une même
unité de travail rejoignent un seul bassin. Chaque service écrit dans l'unité de travail de l'application et ne valide
rien.
