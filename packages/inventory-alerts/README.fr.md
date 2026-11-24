<!-- phil-dev source-sha256=213dee347ba1c1572dd309e01c8a4a7274166f2ffab385e88354bb367c4782ee -->
# phil/inventory-alerts

**Savoir quand réapprovisionner et qui prévenir : points de commande, stock de sécurité, min/max et quantité économique de commande, alertes de stock bas par emplacement, alertes facultatives de retour en stock et de baisse de prix.**

phil/inventory-alerts est une bibliothèque PHP ordinaire. Elle compte en **unités**, jamais en argent : le coût du
stock relève de la comptabilité de l'application.

**Réapprovisionnement.** `ReorderPoint` donne la position de stock à laquelle commander : la demande quotidienne
moyenne pendant le délai d'approvisionnement, plus le stock de sécurité. `SafetyStock` donne ce stock de sécurité par
la **méthode simple** (la pire journée et le plus long délai, moins les moyens) ou par la **méthode statistique** (z ×
l'écart type de la demande pendant le délai, pour un niveau de service tel que 95 %, avec la variation du délai
lui-même quand elle est connue) ; `DemandHistory` donne la moyenne et l'écart de la demande passée.
`EconomicOrderQuantity` donne la quantité qui équilibre la commande et la détention, à partir des coûts que fournit
l'application. Une `ReplenishmentRule` par UGS (unité de gestion de stock) et par emplacement — **min/max**, ou
**quantité fixe** telle que la quantité économique, arrondie à la taille du lot — permet à `ReplenishmentPlanner` de
proposer **un bon de commande par fournisseur**, ses lignes par emplacement. Une proposition attend une personne : elle
est modifiée, approuvée ou rejetée, et la bibliothèque ne passe jamais de commande.

**Stock bas.** `LowStockMonitor` lit le stock après les mouvements et indique au personnel de chaque emplacement
quelles UGS ont atteint le **seuil de cet emplacement** ou sont épuisées, **groupées par emplacement**, chaque alerte
nommant le **mouvement qui l'a déclenchée**. La même situation n'est jamais signalée deux fois : une UGS n'est
signalée de nouveau que si elle s'épuise après avoir été basse, ou si elle rechute après être remontée au-dessus de
son seuil.

**Retour en stock et baisses de prix.** Un client s'abonne explicitement à une UGS. Quand elle revient,
`SubscriberAlerts` le prévient **par vagues, dans l'ordre d'abonnement**, quelques abonnés par unité, pour que trois
unités n'atteignent pas mille personnes d'un coup ; quand son prix tombe au seuil d'un client, le même mécanisme le
prévient. Un abonnement **prend fin une fois son alerte envoyée**, le lien de **désabonnement en un clic** le
supprime, et l'adresse ne sert à rien d'autre. Une alerte dit ce qui s'est passé, jamais combien d'unités restent.

La bibliothèque lit et écrit uniquement par des **ports** — les niveaux de stock, les seuils, les situations
signalées, les abonnements et les deux notificateurs — et ne garde aucun état entre les appels : ses services sont
donc sûrs dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `DemandHistory`, `ServiceLevel`, `SafetyStock`, `ReorderPoint`, `EconomicOrderQuantity` | Implémentés et testés |
| `ReplenishmentRule`, `ReplenishmentPlanner`, `PurchaseProposal` : propositions par fournisseur, approuvées par une personne | Implémentés et testés |
| `LowStockMonitor`, `LowStockDigest` : alertes par emplacement, dédoublonnées, liées à leur mouvement | Implémentés et testés |
| `Subscriptions`, `SubscriberAlerts`, `WavePolicy`, `UnsubscribeTokens` : alertes de retour en stock et de baisse de prix | Implémentés et testés |
| Adaptateurs vers un système de stock ou de courriel | Écrits par l'application |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et la place de chaque service. |
| [Réapprovisionnement](doc/fr/replenishment.md) | Stock de sécurité, point de commande, min/max, quantité économique et propositions d'achat. |
| [Alertes de stock bas](doc/fr/low-stock.md) | Seuils par emplacement, sommaires et moment où une UGS est signalée de nouveau. |
| [Alertes aux abonnés](doc/fr/subscriber-alerts.md) | Abonnements de retour en stock et de baisse de prix, vagues et désabonnement. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et un adaptateur vers un système de stock. |
| [Tests](doc/fr/testing.md) | Les tests, les tests de propriétés et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/inventory-alerts
```

```php
use Phil\InventoryAlerts\Replenishment\DemandHistory;
use Phil\InventoryAlerts\Replenishment\ReorderPoint;
use Phil\InventoryAlerts\Replenishment\ReplenishmentPlanner;
use Phil\InventoryAlerts\Replenishment\ReplenishmentRule;
use Phil\InventoryAlerts\Replenishment\ServiceLevel;

// 28 days of sales, a 6-day lead time, 95 % of cycles without running out.
$point = ReorderPoint::fromHistory(new DemandHistory($dailySales), 6, ServiceLevel::of(0.95));

$proposals = new ReplenishmentPlanner($stockSource)->propose([
    ReplenishmentRule::minMax('MUG-BLUE', 'store', 'acme', minimum: $point, maximum: $point + 60, packSize: 6),
], new DateTimeImmutable());

// Show each proposal to a person; hand $proposal->approve($user, $now) to purchasing.
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-sodium`, et `phil/email-address` pour les adresses des abonnés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
