# Reference

## `Phil\InventoryAlerts\Stock`

| Type | Role |
|---|---|
| `StockLevel` | The units of a SKU at a location: on hand, reserved, on order; `available()`, `position()`, `empty()`. |
| `StockMovement` | A change of the stock, by its identifier: SKU, location, change, date. |

## `Phil\InventoryAlerts\Replenishment`

| Type | Role |
|---|---|
| `DemandHistory` | The demand of each period, 1 to 3,660; `mean()`, `standardDeviation()`, `max()`. |
| `ServiceLevel` | A share of cycles without running out, 0.5 to 0.9999; `of()`, `z()`. |
| `SafetyStock` | `simple()`, `statistical()`, `fromHistory()`: the safety stock in whole units. |
| `ReorderPoint` | `of()`, `fromHistory()`: the position at which to order. |
| `EconomicOrderQuantity` | The quantity that balances ordering and holding; `exact()`, `quantity()`. |
| `ReplenishmentRule` | `minMax()` or `fixedQuantity()` for a SKU at a location; `quantityFor()`. |
| `ReplenishmentPlanner` | Proposes one purchase order per supplier from the rules and the stock. |
| `PurchaseProposal` | A proposed order: supplier, lines, state; `withQuantity()`, `approve()`, `reject()`, `byLocation()`, `totalUnits()`. |
| `ProposalLine` | SKU, location, quantity, position, reorder point. |
| `ProposalState` | `Proposed`, `Approved`, `Rejected`. |

## `Phil\InventoryAlerts\LowStock`

| Type | Role |
|---|---|
| `LowStockMonitor` | Checks the stock after movements and notifies the staff, one digest per location. |
| `LowStockDigest` | The alerts of one location; `movementIds()`. |
| `LowStockAlert` | SKU, location, situation, available units, threshold, movement. |
| `LowStockSituation` | `Low`, `Out`; `of()`. |

## `Phil\InventoryAlerts\Subscription`

| Type | Role |
|---|---|
| `Subscriptions` | `subscribeBackInStock()`, `subscribePriceDrop()`, `unsubscribeToken()`, `unsubscribe()`, `purgeNotified()`. |
| `SubscriberAlerts` | `restocked()`, `priceChanged()`: sends a wave and ends its subscriptions. |
| `Subscription` | Identifier, kind, SKU, address, date, threshold, notification date; `newId()`, `isActive()`, `notified()`. |
| `SubscriptionKind` | `BackInStock`, `PriceDrop`. |
| `Price` | An amount in minor units and an ISO 4217 currency; `isAtOrBelow()`. |
| `Wave` | The subscriptions told together, `full`, the new price; `subscriptionIds()`. |
| `WavePolicy` | Subscribers per unit, minimum, maximum; `sizeFor()`. |
| `UnsubscribeTokens` | `issue()` and `verify()` the signed tokens of the unsubscription links. |

## `Phil\InventoryAlerts\Contract`

| Type | Role |
|---|---|
| `StockLevelSource` | The levels of the stock. |
| `LowStockThresholds` | The threshold of a SKU at a location. |
| `LowStockStateStore` | The situation last told to the staff. |
| `StaffNotifier` | Sends a low-stock digest. |
| `SubscriptionStore` | Keeps the subscriptions. |
| `SubscriberNotifier` | Sends a wave of subscriber alerts. |

## `Phil\InventoryAlerts\Exception`

| Type | Role |
|---|---|
| `InventoryAlertsException` | Every exception of the library. |
| `InvalidInput` | A quantity, a rate, a code or a setting out of range; the message names the field. |
| `ProposalAlreadyDecided` | A proposal changed or decided after its decision. |

## `Phil\InventoryAlerts\Internal`

| Type | Role |
|---|---|
| `Check` | Internal: the checks and the rounding shared by the value objects. |
