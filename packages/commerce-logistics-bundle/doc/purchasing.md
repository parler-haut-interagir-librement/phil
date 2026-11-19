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
| `receive(number, lines, at)` | Receives units against an open order, never more of a SKU than are still expected |
| `close(number)` | Expects nothing more of an open order |
| `onOrder(sku)` | The units of a SKU ordered and not received yet, over the open orders |

- A purchase order is `open` until every unit came — it is then `received` — or until it is closed.
- A SKU the order does not list, a quantity beyond what is still expected, or an order no longer open is refused with
  `PurchaseRefused`; a number used twice too.
- `PurchaseOrder::expectOn()` moves the day the rest is expected.

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
| `phil_commerce_logistics_purchase_order` | `number` (primary key), `supplier`, `location`, `ordered` and `received` (JSON, units by SKU), `state` (indexed), `ordered_at`, `expected_on` |
| `phil_commerce_logistics_serial_number` | `sku` and `serial` (primary key), `location`, `state`, `order_number`, `received_at`, `shipped_at`; indexed by SKU, location and state, and by order |
