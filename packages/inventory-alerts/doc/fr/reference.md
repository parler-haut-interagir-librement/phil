<!-- phil-dev source-sha256=5cb9376998cbdabd3efa8fffc1d4f9ce2fd4320e114c5303c7c4606bda636e7b -->
# Référence

## `Phil\InventoryAlerts\Stock`

| Type | Rôle |
|---|---|
| `StockLevel` | Les unités d'une UGS à un emplacement : en main, réservées, en commande ; `available()`, `position()`, `empty()`. |
| `StockMovement` | Un changement du stock, par son identifiant : UGS, emplacement, variation, date. |

## `Phil\InventoryAlerts\Replenishment`

| Type | Rôle |
|---|---|
| `DemandHistory` | La demande de chaque période, de 1 à 3 660 ; `mean()`, `standardDeviation()`, `max()`. |
| `ServiceLevel` | Une part des cycles sans rupture, de 0,5 à 0,9999 ; `of()`, `z()`. |
| `SafetyStock` | `simple()`, `statistical()`, `fromHistory()` : le stock de sécurité en unités entières. |
| `ReorderPoint` | `of()`, `fromHistory()` : la position à laquelle commander. |
| `EconomicOrderQuantity` | La quantité qui équilibre la commande et la détention ; `exact()`, `quantity()`. |
| `ReplenishmentRule` | `minMax()` ou `fixedQuantity()` pour une UGS à un emplacement ; `quantityFor()`. |
| `ReplenishmentPlanner` | Propose un bon de commande par fournisseur à partir des règles et du stock. |
| `PurchaseProposal` | Une commande proposée : fournisseur, lignes, état ; `withQuantity()`, `approve()`, `reject()`, `byLocation()`, `totalUnits()`. |
| `ProposalLine` | UGS, emplacement, quantité, position, point de commande. |
| `ProposalState` | `Proposed`, `Approved`, `Rejected`. |

## `Phil\InventoryAlerts\LowStock`

| Type | Rôle |
|---|---|
| `LowStockMonitor` | Vérifie le stock après des mouvements et prévient le personnel, un sommaire par emplacement. |
| `LowStockDigest` | Les alertes d'un emplacement ; `movementIds()`. |
| `LowStockAlert` | UGS, emplacement, situation, unités disponibles, seuil, mouvement. |
| `LowStockSituation` | `Low`, `Out` ; `of()`. |

## `Phil\InventoryAlerts\Subscription`

| Type | Rôle |
|---|---|
| `Subscriptions` | `subscribeBackInStock()`, `subscribePriceDrop()`, `unsubscribeToken()`, `unsubscribe()`, `purgeNotified()`. |
| `SubscriberAlerts` | `restocked()`, `priceChanged()` : envoie une vague et met fin à ses abonnements. |
| `Subscription` | Identifiant, type, UGS, adresse, date, seuil, date de notification ; `newId()`, `isActive()`, `notified()`. |
| `SubscriptionKind` | `BackInStock`, `PriceDrop`. |
| `Price` | Un montant en unités mineures et une devise ISO 4217 ; `isAtOrBelow()`. |
| `Wave` | Les abonnements prévenus ensemble, `full`, le nouveau prix ; `subscriptionIds()`. |
| `WavePolicy` | Abonnés par unité, minimum, maximum ; `sizeFor()`. |
| `UnsubscribeTokens` | `issue()` et `verify()` les jetons signés des liens de désabonnement. |

## `Phil\InventoryAlerts\Contract`

| Type | Rôle |
|---|---|
| `StockLevelSource` | Les niveaux du stock. |
| `LowStockThresholds` | Le seuil d'une UGS à un emplacement. |
| `LowStockStateStore` | La situation signalée en dernier au personnel. |
| `StaffNotifier` | Envoie un sommaire de stock bas. |
| `SubscriptionStore` | Garde les abonnements. |
| `SubscriberNotifier` | Envoie une vague d'alertes aux abonnés. |

## `Phil\InventoryAlerts\Exception`

| Type | Rôle |
|---|---|
| `InventoryAlertsException` | Toute exception de la bibliothèque. |
| `InvalidInput` | Une quantité, un taux, un code ou un réglage hors limites ; le message nomme le champ. |
| `ProposalAlreadyDecided` | Une proposition modifiée ou tranchée après sa décision. |

## `Phil\InventoryAlerts\Internal`

| Type | Rôle |
|---|---|
| `Check` | Interne : les vérifications et l'arrondi que se partagent les objets-valeurs. |
