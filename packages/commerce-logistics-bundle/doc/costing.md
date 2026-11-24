# Costing

With costing enabled, the module keeps what the shop's stock is worth at cost as it moves: each receipt, shipment,
count, adjustment, transfer and customer return that `Stock` and `Purchasing` write to the stock journal moves the
value of the stock with it, in cost layers and a cost journal of their own. Reports, a catalogue costing its composite
products, or a margin check read it through `Contract\InventoryValuation`, without writing anything.

Costing is off by default: the module then stores no cost layer and no cost entry, and every answer of
`InventoryValuation` is empty.

```yaml
# config/packages/phil_commerce_logistics.yaml
phil_commerce_logistics:
    costing:
        method: weighted_average   # or fifo: first in, first out
        currency: CAD              # the ISO 4217 code every cost is given in
```

| Configuration | What it sets |
|---|---|
| `costing.enabled` | Turns costing on; giving any other key of `costing` turns it on too |
| `costing.method` | `weighted_average`, the default, or `fifo` |
| `costing.currency` | The currency the stock is valued in, three capital letters; required once costing is on |

## Methods

The method values the shop's own units per SKU and location.

| Method | How units leave |
|---|---|
| `weighted_average` | One pool per SKU and location: its value over its units. A unit leaves at the pool's average, and each receipt changes the average. |
| `fifo` | One layer per receipt, per SKU and location: units leave from the oldest layer first, at that layer's cost. |

```php
$purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(3, Money::ofDecimal('10.00', $cad))], $clock->now());
$purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(2, Money::ofDecimal('12.50', $cad))], $clock->now());
$purchasing->receive('PO-1', ['DICE' => 3], $clock->now());
$purchasing->receive('PO-2', ['DICE' => 2], $clock->now());

$valuation->unitCost('DICE');   // 11.00 CAD with a weighted average; 11.00 too with first in, first out, over its layers
// Four dice ship: 44.00 CAD with a weighted average, 30.00 + 12.50 = 42.50 CAD first in, first out.
```

- **Integers only.** Every amount is a `Money` in minor units; a part of a value is shared by `Money::allocate()`, so the
  units that leave a layer one at a time take exactly what the layer held, to the minor unit, the last one taking what
  is left. A unit cost read from a pool is rounded half up.
- **A unit cost comes from the purchase order line** — `PurchaseLine::of(units, unitCost)`, see
  [Purchasing](purchasing.md) — or from `Stock::receive(..., unitCost: $cost)` for a receipt outside a purchase order.
  It is zero or more, in the configured currency, refused with `CostRefused` otherwise.
- **Units received without a unit cost, or before costing was enabled, are uncosted**: they move no value, and the cost
  journal counts them apart, in `uncosted`. Units leave the costed layers first.
- **Units found by a count or an adjustment** come in at the level's current cost: the pool's average, or the newest
  layer's under first in, first out; uncosted when the level holds no costed unit.
- **A transfer takes cost and owner along.** Under first in, first out each layer keeps the moment it was received, so
  the units keep their place in line at the new location; under a weighted average they join the target's pool.
- **Changing the method** leaves the layers as they are: a weighted average merges the layers first in, first out left
  at a SKU and location into one pool the first time it moves them.

## Landed costs

The freight, duties and brokerage of a receipt are shared out over its lines:

```php
$purchasing->receive('PO-1', ['BOOK' => 3, 'ZINE' => 1], $clock->now(), landedCost: Money::ofDecimal('1.00', $cad));
// BOOK: 3 × 10.00 = 30.00 and ZINE: 5.00 bear 1.00 in proportion: 0.86 and 0.14.
```

- The landed cost is shared over the lines of this receipt whose units are the shop's and have a unit cost, in
  proportion to their value — units × unit cost — through `Money::allocate()`: the shares add up to the landed cost to
  the minor unit, the minor units left going to the largest remainders. When every bearing line costs nothing, the
  shares follow their units.
- Each share is added to the layer of that receipt under first in, first out, or to the pool under a weighted average,
  and journaled as `landed`.
- A landed cost needs costing (`CostRefused::notCosted()`), a line that bears it (`CostRefused::noLineBears()`), and the
  configured currency; it is zero or more.

## Variances

A count or an adjustment that finds units missing or extra may say why, by a `Domain\Cost\VarianceReason`:

```php
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;

$stock->adjustFor('DICE', 'warehouse', -1, VarianceReason::Theft, $clock->now(), 'COUNT-12');
$stock->count('DICE', 'store', 18, $clock->now(), 'COUNT-13', VarianceReason::CountError);
```

| Reason | Value | When |
|---|---|---|
| `Damaged` | `damaged` | Broken or spoiled on the shelf, in the stockroom or in handling |
| `Theft` | `theft` | Stolen |
| `Lost` | `lost` | Gone without a known cause |
| `Expired` | `expired` | Past its date, no longer sold |
| `CountError` | `count_error` | An earlier count, receipt or shipment counted wrong |
| `SupplierError` | `supplier_error` | The supplier sent fewer, more or other units than its papers say |
| `InternalUse` | `internal_use` | Taken for the shop's own use: a demo, a sample, a display |
| `Donated` | `donated` | Given away: a donation, a prize |

- The reason goes to the stock journal, in `StockMovement::$reason`, with or without costing; `adjust()` and a `count()`
  without one leave it null.
- With costing, the cost journal keeps the variance as `variance`, with its reason and the value of the units at cost:
  what a shrink report reads, through `InventoryValuation::entries()`.

## Customer returns

`Stock::returnFromCustomer()` puts the units back at the cost they left at, never at the day's cost: their share of
what the order's issues of that SKU cost, less what earlier returns took back, through `Money::allocate()`. It works
the same under both methods: under first in, first out, the units come back as a layer of their own, received at the
moment of the return.

- Units the cost journal never saw leave — a sale shipped before costing was enabled — come back uncosted.
- Units of an owner come back to that owner, as far as the order's issues tell; see below.
- `phil/commerce-returns-bundle` restocks through `returnFromCustomer()` when its logistics restocking is enabled.

## Owners and consignment

Units received with an owner — `PurchaseLine::of(units, unitCost, owner)`, or `Stock::receive(..., owner: 'consignor-17')`
— are left on consignment: they go to a layer of that owner, which holds units and no value. They never enter the
shop's layers nor its value, whatever their purchase order says they cost, and no landed cost reaches them.

- **When units leave a level that holds the shop's units and an owner's**, `Contract\StockOwnership` says whose leave.
  `ShopUnitsFirst` is the module's answer: the shop's own units first, then each owner's, the owner whose units came
  first first. An application, or a consignment module, points the alias of `StockOwnership` at its own service.
- `StockOwnership::leaving()` is given the units leaving, the shop's units at the level, each owner and their units,
  and what they leave for; it answers each owner and how many of their units leave. An answer that gives an owner
  more than they hold, or leaves the shop more units than it holds, is refused with `CostRefused::share()`.
- Each movement of an owner's units is journaled with the owner, its value always zero: `InventoryValuation` never
  counts them.

## Reading the value

`Contract\InventoryValuation`, a public alias of `InventoryValue`, is what an application reads. Calling `Costing`
itself would count a movement twice.

| Method | What it gives |
|---|---|
| `unitCost(sku)` | What one unit costs the shop now: the value of its costed units over their number, at every location, rounded half up; with none on hand, the unit cost of its latest receipt at a known cost; null when it never had one |
| `valuedStock(at)` | `ValuedStock` per SKU and location: the shop's costed units and their value at a moment, by SKU then location |
| `costOfSales(from, until)` | `SaleCost` per sale and SKU: the units its issues took less those that came back in the period, the uncosted among them, and their cost |
| `entries(from, until, kinds, after, limit)` | The cost journal of a period, of some kinds or all, in the order it was written, 1 to 1,000 entries a page after the identifier of the last entry of the previous page |

A period runs from its start, included, to its end, excluded; moments are compared in UTC.

## Storage

| Table | Holds |
|---|---|
| `phil_commerce_logistics_cost_layer` | `id` (UUID v7), `sku`, `location`, `owner` (null for the shop), `units`, `value` (minor units), `currency`, `received_at`, `reference`; indexed by SKU and location. A layer emptied is deleted. |
| `phil_commerce_logistics_cost_entry` | `id` (UUID v7), `sku`, `location`, `kind`, `owner`, `units`, `uncosted`, `value` (minor units, signed), `currency`, `reason`, `reference`, `moved_at`; indexed by SKU, location and moment, by reference, and by moment. Never changed, never deleted. |

The `reason` column of `phil_commerce_logistics_stock_movement` is nullable. A database of 0.1.0-alpha gains the two
tables and that column, and nothing else changes; see [Stock](stock.md#storage).

The cost layers and entries given to `DoctrineLogisticsRepository` and not flushed yet are found as if they were
stored, and a layer removed and not flushed yet is no longer found: two receipts within one unit of work join one
pool. Every service writes within the application's unit of work and commits nothing.
