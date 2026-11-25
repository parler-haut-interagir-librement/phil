# Installation

## Requirements

- PHP 8.5 or later, with `ext-sodium`.
- `phil/email-address`, which parses the address of a subscriber.
- No framework and no database: the library reads and writes only through the ports an application implements.

## Installing

```sh
composer require phil/inventory-alerts
```

The library has no configuration of its own. Its services are plain objects built with their ports, and they keep no
state between calls: `ReplenishmentPlanner`, `LowStockMonitor`, `Subscriptions` and `SubscriberAlerts` are safe to
share in a long-running worker. A framework registers them as services.

## Where each service fits

| When | Service | Ports it needs |
|---|---|---|
| A nightly job, or a person asks for the orders to place | `ReplenishmentPlanner` | `StockLevelSource` |
| After a sale, a reservation, a count or a transfer | `LowStockMonitor` | `StockLevelSource`, `LowStockThresholds`, `LowStockStateStore`, `StaffNotifier` |
| A customer subscribes, or follows an unsubscription link | `Subscriptions` | `SubscriptionStore`, `UnsubscribeTokens` |
| Stock is received, or a price changes | `SubscriberAlerts` | `SubscriptionStore`, `SubscriberNotifier` |

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

The signing key of `UnsubscribeTokens` is a key of its own, never the application secret; it comes from the secrets
of the application, never from its code, and changing it makes the links already sent invalid. [Extension points](extension-points.md) shows each port.
