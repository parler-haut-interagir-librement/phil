# Purchasing and serial numbers

## Purchase orders

`Purchasing` orders goods from suppliers and receives them against their purchase orders. Each receipt adds the units
to stock at the order's location, in the journal under the order's number.

```php
$purchasing->order('PO-2026-014', 'Dice Works', 'warehouse', ['DICE-D20' => 120, 'DICE-D6' => 60], $clock->now(), expectedOn: new DateTimeImmutable('2026-11-20'));
$purchasing->onOrder('DICE-D20');                                       // 120
$purchasing->receive('PO-2026-014', ['DICE-D20' => 80], $clock->now()); // 80 on hand, 40 still expected
$purchasing->close('PO-2026-014');                                      // the rest will not come
```

| Method of `Purchasing` | Effect |
|---|---|
| `order(number, supplier, location, lines, at, expectedOn)` | Opens a purchase order of 1 to 500 SKUs, each one unit or more, at a known location |
| `receive(number, lines, at, landedCost)` | Receives units against an open order, never more of a SKU than are still expected; with costing, shares the landed cost over its lines |
| `close(number)` | Expects nothing more of an open order |
| `onOrder(sku)` | The units of a SKU ordered and not received yet, over the open orders |

- A purchase order is `open` until every unit came — it is then `received` — or until it is closed.
- A SKU the order does not list, a quantity beyond what is still expected, or an order no longer open is refused with
  `PurchaseRefused`; a number used twice too.
- `PurchaseOrder::expectOn()` moves the day the rest is expected.

### Unit cost and owner

A line may give what each unit costs and who owns the units, through `PurchaseLine` in place of the units alone:

```php
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;

$purchasing->order('PO-2026-021', 'Books & Co', 'warehouse', [
    'BOOK-17' => PurchaseLine::of(5, Money::ofDecimal('12.50', Currency::of('CAD'))),   // bought
    'ZINE-03' => PurchaseLine::of(10, owner: 'consignor-42'),                           // left on consignment
    'DICE-D6' => 60,                                                                    // units only
], $clock->now());

$order = $purchasing->receive('PO-2026-021', ['BOOK-17' => 5], $clock->now());
$order->line('BOOK-17');   // PurchaseLine: 5 units, 12.50 CAD each, no owner
```

- **The unit cost** is a `Money`, zero or more, in the currency the supplier invoices; the module stores it and gives it
  back. With costing enabled, the units received come in at that cost, which must then be in the configured currency,
  and a landed cost passed to `receive()` is shared over the lines — see [Costing](costing.md).
- **The owner** is the caller's own identifier — a consignor, a supplier, a department —, 1 to 64 characters without a
  control character, kept as it is given; the module knows nothing of owners. A purchase order has one line per SKU,
  so one owner per SKU: units of one SKU owned by two parties go on two purchase orders. With costing, an owner's units
  never enter the shop's value, whatever their unit cost.
- **Both are optional**, line by line; `PurchaseOrder::line()` gives the units ordered, the unit cost and the owner of a
  SKU, or null for a SKU the order does not list. `receive()` gives back the order, from which a ledger reads what the
  units that came cost and whom they belong to.
- The units of a line are bound as the units alone are: one or more, refused with `InvalidValue` otherwise.

Supplier returns, replenishment and forecasts are not part of the module.

## Serial numbers

`SerialNumbers` keeps the serial number of each unit with its stock: registered at the location that holds it, moved
with a transfer, recorded against the order it leaves with, and put back on a shelf when it comes back.

```php
$stock->receive('CONSOLE', 'warehouse', 3, $clock->now(), 'PO-2026-020');
$serials->register('CONSOLE', 'warehouse', ['SN-0001', 'SN-0002', 'SN-0003'], $clock->now());
$serials->ship($order->number, 'CONSOLE', ['SN-0002'], $clock->now());
$serials->ofOrder($order->number);   // [SerialNumber SN-0002]
$serials->restock('CONSOLE', 'SN-0002', 'store');
```

- **Never more serialised units at a location than it holds on hand**; a serial number is registered once per SKU.
- A unit moves or ships only while in stock, and only from where it is; a unit is put back only once it left.
- A call names 1 to 1,000 serial numbers, each once; a serial number is 1 to 128 visible ASCII characters.
- The module does not tie a unit to a shipment's stock movement: the application records the units with the shipment
  that took them, through `ship()`.

## Storage

| Table | Holds |
|---|---|
| `phil_commerce_logistics_purchase_order` | `number` (primary key), `supplier`, `location`, `ordered` and `received` (JSON, units by SKU), `line_terms` (JSON, the unit cost in minor units, its currency and the owner of the SKUs whose line gives them; null when none does), `state` (indexed), `ordered_at`, `expected_on` |
| `phil_commerce_logistics_serial_number` | `sku` and `serial` (primary key), `location`, `state`, `order_number`, `received_at`, `shipped_at`; indexed by SKU, location and state, and by order |
