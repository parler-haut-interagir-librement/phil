# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. The calculations are checked against values worked out by hand,
written beside each assertion. `tests/Support` holds the ports in memory: `MemoryStock` (levels and thresholds, with
the number of reads), `MemoryLowStockStates`, `MemorySubscriptions` (ordered as a table would order them), and two
notifiers that remember what they send or fail on demand. In the monorepo, Infection holds the package at 100 % of
covered mutants.

## The property tests

`tests/Unit/Replenishment/PropertiesTest.php` draws 500 inputs from fixed seeds and checks, for each:

- the reorder point less the safety stock covers the lead-time demand by less than one unit;
- the reorder point never falls when the demand or the lead time rises, and follows the safety stock unit for unit;
- at the economic order quantity, ordering and holding cost the same, and no other quantity costs less;
- four times the demand doubles the economic order quantity;
- the whole quantity is the smallest multiple of the pack at or above the exact one.

## Testing an application's adapters

An adapter is tested with the service it serves and the other ports in memory:

```php
use Phil\InventoryAlerts\LowStock\LowStockMonitor;
use Phil\InventoryAlerts\Stock\StockMovement;

$monitor = new LowStockMonitor(new LogisticsStockSource($repository, $purchaseOrders), $thresholds, $states, $notifier);
$digests = $monitor->record([new StockMovement('m1', 'MUG-BLUE', 'store', -3, new DateTimeImmutable())]);
```

## The benchmark

`tests/Benchmark/AlertsBench.php` measures the hot paths on 10,000 SKUs and locations: the purchase proposals of a
night run over 25 suppliers, and the low-stock check of 10,000 movements.

```sh
composer bench
```
