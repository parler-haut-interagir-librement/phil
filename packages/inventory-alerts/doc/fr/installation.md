<!-- phil-dev source-sha256=79701d91d1678153ed1201e130ade11cc12d2f8f0faeaf28fbac7d86dc7eaed5 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-sodium`.
- `phil/email-address`, qui analyse l'adresse d'un abonné.
- Ni cadriciel ni base de données : la bibliothèque lit et écrit uniquement par les ports qu'une application
  implémente.

## Installer

```sh
composer require phil/inventory-alerts
```

La bibliothèque n'a aucune configuration propre. Ses services sont des objets ordinaires construits avec leurs ports,
et ils ne gardent aucun état entre les appels : `ReplenishmentPlanner`, `LowStockMonitor`, `Subscriptions` et
`SubscriberAlerts` se partagent sans risque dans un *worker* de longue durée. Un cadriciel les enregistre comme
services.

## La place de chaque service

| Quand | Service | Ports requis |
|---|---|---|
| Une tâche de nuit, ou une personne demande les commandes à passer | `ReplenishmentPlanner` | `StockLevelSource` |
| Après une vente, une réservation, un décompte ou un transfert | `LowStockMonitor` | `StockLevelSource`, `LowStockThresholds`, `LowStockStateStore`, `StaffNotifier` |
| Un client s'abonne, ou suit un lien de désabonnement | `Subscriptions` | `SubscriptionStore`, `UnsubscribeTokens` |
| Du stock est reçu, ou un prix change | `SubscriberAlerts` | `SubscriptionStore`, `SubscriberNotifier` |

```php
use Phil\InventoryAlerts\LowStock\LowStockMonitor;
use Phil\InventoryAlerts\Subscription\SubscriberAlerts;
use Phil\InventoryAlerts\Subscription\Subscriptions;
use Phil\InventoryAlerts\Subscription\UnsubscribeTokens;
use Phil\InventoryAlerts\Subscription\WavePolicy;

$monitor = new LowStockMonitor($stockSource, $thresholds, $lowStockStates, $staffNotifier);
$subscriptions = new Subscriptions($subscriptionStore, new UnsubscribeTokens($signingKeyOfAtLeast32Bytes));
$alerts = new SubscriberAlerts($subscriptionStore, $subscriberNotifier, new WavePolicy(subscribersPerUnit: 2, minimum: 10, maximum: 500));
```

La clé de signature de `UnsubscribeTokens` est une clé à part, jamais le secret de l'application ; elle vient des
secrets de l'application, jamais de son code, et la changer invalide les liens déjà envoyés.
[Points d'extension](extension-points.md) présente chaque port.
