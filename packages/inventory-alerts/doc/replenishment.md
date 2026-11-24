# Replenishment

Every quantity is in whole units. A computed quantity is rounded up — a safety stock of 49.3 units is 50 —, after the
rounding errors of floating-point arithmetic are set aside: 0.1 × 30 is 3 units, not 4. The demand is per day and the
lead time in days, unless the application keeps another period for both.

## Demand history

`DemandHistory` holds the demand of each period, oldest first, from 1 to 3,660 periods, each 0 or more.

```php
use Phil\InventoryAlerts\Replenishment\DemandHistory;

$history = new DemandHistory([4, 6, 5, 7, 3]);
$history->mean();              // 5.0
$history->standardDeviation(); // 1.58: the sample deviation, √(10 ÷ 4)
$history->max();               // 7.0
```

## Safety stock

The **simple method** keeps what the worst case would use beyond the average case, never less than 0:

```text
safety stock = max daily demand × max lead time − average daily demand × average lead time
```

```php
use Phil\InventoryAlerts\Replenishment\SafetyStock;

SafetyStock::simple(maxDailyDemand: 20, maxLeadTimeDays: 10, averageDailyDemand: 12, averageLeadTimeDays: 7); // 116
```

The **statistical method** keeps enough for a service level — the share of replenishment cycles that end without
running out:

```text
safety stock = z × √(lead time × σ(demand)² + average demand² × σ(lead time)²)
```

`z` is the quantile of the normal distribution for the service level: 1.2816 for 90 %, 1.6449 for 95 %, 2.3263 for
99 %. When the lead time does not vary, the formula is z × σ(demand) × √(lead time).

```php
use Phil\InventoryAlerts\Replenishment\ServiceLevel;

$level = ServiceLevel::of(0.95);   // from 0.5 to 0.9999
$level->z();                       // 1.6448536

SafetyStock::statistical($level, demandStandardDeviation: 10, leadTimeDays: 9);                                                 // 50
SafetyStock::statistical($level, demandStandardDeviation: 10, leadTimeDays: 9, averageDailyDemand: 20, leadTimeStandardDeviation: 2); // 83
SafetyStock::fromHistory($history, $level, leadTimeDays: 4);                                                                   // 6
```

## Reorder point

```text
reorder point = average daily demand × lead time + safety stock
```

```php
use Phil\InventoryAlerts\Replenishment\ReorderPoint;

ReorderPoint::of(averageDailyDemand: 12, leadTimeDays: 7, safetyStock: 116); // 200
ReorderPoint::fromHistory($history, leadTimeDays: 4, serviceLevel: ServiceLevel::of(0.95)); // 26
```

## Economic order quantity

```text
EOQ = √(2 × annual demand × cost of an order ÷ annual cost of holding a unit)
```

The two costs are inputs the application supplies, in the same currency; the library keeps no cost and returns
units only.

```php
use Phil\InventoryAlerts\Replenishment\EconomicOrderQuantity;

$eoq = new EconomicOrderQuantity(annualDemand: 1_200, orderCost: 50, annualHoldingCost: 3);
$eoq->exact();     // 200.0
$eoq->quantity(12); // 204: rounded up to whole packs of 12
```

## Rules

A `ReplenishmentRule` says how one SKU is replenished at one location, and from which supplier. Its quantity is
always rounded up to the pack size.

- **Min/max** — `ReplenishmentRule::minMax($sku, $location, $supplier, $minimum, $maximum, $packSize)`: at or under
  the minimum, order up to the maximum.
- **Fixed quantity** — `ReplenishmentRule::fixedQuantity($sku, $location, $supplier, $reorderPoint, $orderQuantity,
  $packSize)`: at or under the reorder point, order the quantity — such as the EOQ —, as many times as it takes to
  rise above the reorder point.

The rule decides on the **inventory position**: on hand, less reserved, plus on order. Units already ordered are not
ordered again.

## Purchase proposals

`ReplenishmentPlanner::propose()` reads the level of each rule's SKU and location, and returns one `PurchaseProposal`
per supplier, in the order of each supplier's first rule, with its lines sorted by location then SKU. A location that
never held the SKU has a position of 0. At most 100,000 rules are read in one call, one per SKU and location.

```php
use Phil\InventoryAlerts\Replenishment\ReplenishmentPlanner;

foreach (new ReplenishmentPlanner($stockSource)->propose($rules, $now) as $proposal) {
    // $proposal->supplier, $proposal->lines (sku, location, quantity, position, reorderPoint), $proposal->byLocation()
}
```

A proposal waits for a person. Before deciding, the person may change the quantity of a line; a quantity of 0 drops
it, and the last line is not dropped — the proposal is rejected instead.

```php
$proposal = $proposal->withQuantity('MUG-BLUE', 'store', 48);
$approved = $proposal->approve('user-42', $now);   // or ->reject('user-42', $now)
```

A decided proposal cannot be changed or decided again (`ProposalAlreadyDecided`). The library never places an order:
the application keeps the proposals, and hands an approved one to its purchasing.
