# phil/inventory-alerts

**Know when to reorder and whom to tell: reorder points, safety stock, min/max and EOQ, low-stock alerts per
location, opt-in back-in-stock and price-drop alerts.**

phil/inventory-alerts is a plain PHP library. It works in **units**, never in money: the cost of the stock belongs to
the accounting of the application.

**Replenishment.** `ReorderPoint` gives the inventory position at which to order: the average daily demand over the lead
time, plus the safety stock. `SafetyStock` gives that safety stock by the **simple method** (the worst day and the
longest lead time, less the average ones) or by the **statistical method** (z × the standard deviation of the demand
over the lead time, for a service level such as 95 %, the lead time's own variation included when it is known);
`DemandHistory` gives the mean and the deviation of past demand. `EconomicOrderQuantity` gives the quantity that
balances ordering and holding, from the costs the application supplies. A `ReplenishmentRule` per SKU and location —
**min/max**, or a **fixed quantity** such as the EOQ, rounded to the pack size — lets `ReplenishmentPlanner` propose
**one purchase order per supplier**, its lines by location. A proposal waits for a person: it is changed, approved or
rejected, and the library never places an order.

**Low stock.** `LowStockMonitor` reads the stock after movements and tells the staff of each location which SKUs
reached the **threshold of that location** or ran out, **grouped by location**, each alert naming the **movement that
raised it**. The same situation is never told twice: a SKU is told again only when it runs out after being low, or
falls again after rising above its threshold.

**Back in stock and price drops.** A customer subscribes explicitly to a SKU. When it is back, `SubscriberAlerts`
tells them **by waves in the order of subscription**, a few subscribers per unit, so three units do not reach a
thousand people at once; when its price falls to a customer's threshold, the same mechanism tells them. A
subscription **ends once its alert is sent**, the **one-click unsubscription** link deletes it, and the address serves
no other purpose. An alert says what happened, never how many units are left.

The library reads and writes only through **ports** — the stock levels, the thresholds, the situations told, the
subscriptions, and the two notifiers — and keeps no state between calls, so its services are safe in a long-running
worker.

## Status

| Part | State |
|---|---|
| `DemandHistory`, `ServiceLevel`, `SafetyStock`, `ReorderPoint`, `EconomicOrderQuantity` | Implemented and tested |
| `ReplenishmentRule`, `ReplenishmentPlanner`, `PurchaseProposal`: proposals per supplier, approved by a person | Implemented and tested |
| `LowStockMonitor`, `LowStockDigest`: alerts per location, deduplicated, linked to their movement | Implemented and tested |
| `Subscriptions`, `SubscriberAlerts`, `WavePolicy`, `UnsubscribeTokens`: back-in-stock and price-drop alerts | Implemented and tested |
| Adapters to a stock or a mail system | Written by the application |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and where each service fits. |
| [Replenishment](doc/replenishment.md) | Safety stock, reorder point, min/max, EOQ, and purchase proposals. |
| [Low-stock alerts](doc/low-stock.md) | Thresholds per location, digests, and when a SKU is told again. |
| [Subscriber alerts](doc/subscriber-alerts.md) | Back-in-stock and price-drop subscriptions, waves, and unsubscription. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and an adapter to a stock system. |
| [Testing](doc/testing.md) | The tests, the property tests and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

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

## Requirements

PHP 8.5 or later, with `ext-sodium`, and `phil/email-address` for the addresses of the subscribers.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
