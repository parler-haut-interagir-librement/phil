# Low-stock alerts

## Thresholds and situations

Each SKU has a threshold at each location where somebody watches it, given by `LowStockThresholds`: the staff sets it,
or the application takes the reorder point. A SKU nobody watches at a location is not read there.

The **available** units are those on hand less those reserved. Against the threshold, a SKU is:

| Available units | Situation |
|---|---|
| above the threshold | none |
| at the threshold or under it, above 0 | `LowStockSituation::Low` |
| 0 or under | `LowStockSituation::Out` |

## Recording movements

The application gives `LowStockMonitor::record()` the movements it has written — a sale, a reservation, a count,
a transfer —, at most 10,000 in one call. For each SKU and location, the monitor reads the level after them and
compares its situation with the one last told, kept by `LowStockStateStore`.

```php
use Phil\InventoryAlerts\Stock\StockMovement;

$digests = $monitor->record([
    new StockMovement($movement->id, 'MUG-BLUE', 'store', -3, $movedAt),
]);
```

- A new situation — low, out, or low again — raises an alert. It names the available units, the threshold and the
  **movement that raised it**: of several movements of a SKU at a location in one call, the last one.
- The same situation is **never told twice**: a SKU that stays low through ten sales raises one alert. Running out
  after being low is a new situation.
- A SKU that rises above its threshold is forgotten, so it is told again when it falls back.

## Digests by location

The alerts are grouped by location into a `LowStockDigest`, in the order of the movements, and each digest is sent
by `StaffNotifier`: the staff of a store hears about the store only. A situation is remembered once its digest is
sent; if the notifier throws, that location's situations are not remembered and the next movement tells them again.

```php
foreach ($digests as $digest) {
    // $digest->location, $digest->alerts (sku, situation, available, threshold, movement), $digest->movementIds()
}
```

A notifier that hands the digest to a queue puts the location and `movementIds()` in the message; the handler reads
the stock again.
