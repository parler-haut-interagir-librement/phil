# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Contract\StockLevelSource` | an adapter to the stock records | gives the level of a SKU at a location |
| `Contract\LowStockThresholds` | the application | gives the low-stock threshold of a SKU at a location |
| `Contract\LowStockStateStore` | a table or a cache of the application | keeps the situation last told to the staff |
| `Contract\StaffNotifier` | the application's mail or notifications | sends a digest to the staff of a location |
| `Contract\SubscriptionStore` | a table of the application | keeps the subscriptions, in order |
| `Contract\SubscriberNotifier` | the application's mail | sends a wave of alerts, with the unsubscription links |

## The stock source

`StockLevelSource::level()` returns a `StockLevel` — on hand, reserved, on order — or null when the location never
held the SKU. An adapter to a stock bundle that keeps its levels by SKU and location, such as
`phil/commerce-logistics-bundle`, maps one level to another; the library never depends on that bundle.

```php
use Phil\InventoryAlerts\Contract\StockLevelSource;
use Phil\InventoryAlerts\Stock\StockLevel;

final readonly class LogisticsStockSource implements StockLevelSource
{
    public function __construct(
        private StockRepository $stock,          // the stock repository of the logistics bundle
        private PurchaseOrders $purchaseOrders,  // what is ordered and not received yet
    ) {
    }

    public function level(string $sku, string $location): ?StockLevel
    {
        $level = $this->stock->level($sku, $location);

        return null === $level ? null : new StockLevel(
            $sku,
            $location,
            max(0, $level->onHand()),
            max(0, $level->reserved()),
            $this->purchaseOrders->openQuantity($sku, $location),
        );
    }
}
```

The movements given to `LowStockMonitor::record()` come from the same records: each written movement becomes a
`StockMovement` with its identifier, SKU, location, change and date, after the transaction that wrote it.

## The low-stock state

`LowStockStateStore` keeps one row per SKU and location told: `situation()` reads it, `remember()` writes it after
the digest is sent, `forget()` deletes it when the stock rises above its threshold. A table with a primary key on SKU
and location, or a cache without expiry, both fit.

## The notifiers

`StaffNotifier::notify()` sends one `LowStockDigest`; `SubscriberNotifier::notify()` sends one `Wave`. Either may
send at once or hand the work to a queue. A message put on a queue carries identifiers only: the location and the
movement identifiers of a digest, the subscription identifiers of a wave, read back by the handler. An address never
travels in a message.

A subscriber notifier builds each message from the subscription: the SKU, the kind, the new price of a price drop,
and the unsubscription link from `Subscriptions::unsubscribeToken()`, in the body and in the `List-Unsubscribe` and
`List-Unsubscribe-Post` headers. It adds nothing else to the address's history.

## The subscription store

`SubscriptionStore` keeps what a `Subscription` holds and nothing more: identifier, kind, SKU, address
(`EmailAddress::toString()`), subscription date, threshold amount and currency, notification date.

- `save()` inserts or replaces by identifier; `find()` and `findActive()` read one.
- `active()` returns the active subscriptions of a kind and SKU **ordered by subscription date then identifier**, at
  most `$limit`; for a price drop, only those whose threshold the price is at or below, in the same currency. An index
  on kind, SKU, notification date and subscription date serves it.
- `markNotified()` sets the notification date of the identifiers it is given.
- `delete()` and `deleteNotifiedBefore()` delete rows, address included.

## Options

- `WavePolicy`: subscribers per unit (2), minimum of a wave (10), maximum of a wave (500, at most 10,000).
- `UnsubscribeTokens`: the signing key, 32 bytes or more.
- `ReplenishmentRule`: min/max or fixed quantity, pack size.
