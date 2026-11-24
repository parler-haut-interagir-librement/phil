# Changelog

All notable changes to `phil/inventory-alerts` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-24

### Added

- `StockLevel` and `StockMovement`: the units on hand, reserved and on order of a SKU at a location, the available
  units and the inventory position; a change of the stock by its identifier.
- `DemandHistory` and `ServiceLevel`: the mean, sample standard deviation and maximum of 1 to 3,660 periods of
  demand; a service level from 50 % to 99.99 % and its normal quantile, within about 1e-9.
- `SafetyStock`, `ReorderPoint` and `EconomicOrderQuantity`: the safety stock by the simple method (worst case less
  average case) or the statistical method (z × √(lead time × σ(demand)² + demand² × σ(lead time)²)), the reorder
  point, and the economic order quantity from costs the application supplies, all in whole units rounded up, a pack
  size respected.
- `ReplenishmentRule`, `ReplenishmentPlanner`, `PurchaseProposal`, `ProposalLine` and `ProposalState`: min/max and
  fixed-quantity rules on the inventory position; one proposed purchase order per supplier, lines sorted by location
  then SKU, at most 100,000 rules a call; lines changed, then approved or rejected by a person, never ordered by the
  library.
- `LowStockMonitor`, `LowStockDigest`, `LowStockAlert` and `LowStockSituation`: low and out-of-stock alerts at the
  threshold of each location, grouped by location, linked to the movement that raised them, told again only when the
  situation changes, remembered once their digest is sent; at most 10,000 movements a call.
- `Subscriptions`, `Subscription`, `SubscriptionKind`, `Price` and `UnsubscribeTokens`: explicit back-in-stock and
  price-drop subscriptions, one active per address, SKU and kind; one-click unsubscription by a token signed with a
  keyed SHA-256 hash, which deletes the subscription; notified subscriptions purged.
- `SubscriberAlerts`, `Wave` and `WavePolicy`: alerts sent by waves in the order of subscription, sized by the units
  back in stock between a minimum and a maximum, or to the subscribers whose price threshold is reached; each
  subscription ended once its wave is sent; no quantity left in an alert.
- The ports `StockLevelSource`, `LowStockThresholds`, `LowStockStateStore`, `StaffNotifier`, `SubscriptionStore` and
  `SubscriberNotifier`, and the exceptions `InventoryAlertsException`, `InvalidInput` and `ProposalAlreadyDecided`.
- The benchmark of the purchase proposals and the low-stock check on 10,000 SKUs and locations, and the property
  tests of the reorder point and the economic order quantity.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
