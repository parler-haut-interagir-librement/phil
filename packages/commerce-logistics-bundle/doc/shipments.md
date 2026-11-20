# Shipments

An order leaves in one parcel or several. Each shipment names how many units of which lines leave, takes them off
the shelf from the order's reservations, and keeps the carrier and the tracking number.

```php
$shipments->ship($order, $clock->now(), ['product:DICE' => 4], carrier: 'Canada Post', tracking: 'CP123456789CA');
$shipments->left($order);    // ['product:DICE' => 2, 'product:MAP' => 1]
$shipments->state($order);   // FulfilmentState::Partial
$shipments->ship($order, $clock->now());   // everything left
```

- Lines are named as the order names them, `type:id`. Only a placed order ships, and no line ships more than is left
  of it.
- A `product` line takes its units from the order's reservations, in the order they were made; a line of another
  type, or a product not tracked, leaves without touching the stock.
- Shipping more of a SKU than its reservations still hold is refused; releasing the order afterwards gives back only
  what is still held.

## Delivery notes and picking slips

`ShippingDocuments` issues the delivery note of a shipment and the picking slip of an order. `LogisticsDocumentTypes`
declares both to the core's register of document types, tagged `phil_commerce.document_types`, each numbered in its
own series without gaps: `DN-00000001`, `PS-00000001` by default.

```php
$slip = $entityManager->wrapInTransaction(fn () => $documents->pickingSlip($order, $clock->now()));
$slip->lines;         // [PickingLine('DICE', 'store', 2), PickingLine('DICE', 'warehouse', 1), PickingLine('MAP', null, 2)]
$slip->giftWrapped;   // true: wrap it, in recycled wrap when $slip->recycledWrap

$note = $entityManager->wrapInTransaction(fn () => $documents->deliveryNote($order, $shipment, $clock->now()));
$note->lines;         // [DeliveryNoteLine('product:DICE', 'Dice', 2, Money 10.00 CAD), …]
$note->giftMessage;   // 'Bonne fête!'
```

- **A number is drawn inside the transaction that stores the document**, as the core's register asks: wrap the call in
  `wrapInTransaction()`. A document is numbered once — one delivery note per shipment, one picking slip per order —,
  and issuing it again gives back its number and moment, with what the order holds now.
- **The picking slip** lists the units still held for the order, by location then by SKU, and last the product lines
  whose stock is not tracked, without a location. It says whether to wrap the order, and in which wrap.
- **The delivery note** lists what the parcel holds of each line, with the carrier and tracking number, and the gift
  options of the order: the wrapping, the message for the person who receives it, and no unit price when the order
  hides its prices. The core charges the wrapping itself, through its `GiftWrapping` processor.
- `DeliveryNote` and `PickingSlip` are what the application prints, in the template it chooses.

## Shipping waves

`ShippingWaves` plans orders that leave together on an estimated day — the goods of a preorder arriving, a print run
—, shows the buyer that day, and releases the wave when it comes.

```php
$waves->plan('january', 'January run', new DateTimeImmutable('2027-01-15'));
$waves->assign('2026-0412', 'january');
$waves->estimatedOn('2026-0412');            // 2027-01-15
$waves->reschedule('january', new DateTimeImmutable('2027-01-29'));
$orders = $waves->release('january', $clock->now());   // the numbers of its orders, ready to ship
```

- An order is in one wave at most: assigning it again moves it, `withdraw()` takes it out.
- A released wave keeps its orders and its day: it is no longer assigned to, withdrawn from, rescheduled or released.
- `planned()` lists the waves not released yet, the earliest first.

## Storage

| Table | Holds |
|---|---|
| `phil_commerce_logistics_shipment` | `id` (UUID v7), `order_number`, `line_items` (JSON, units by line), `shipped_at`, `carrier`, `tracking`; indexed by order |
| `phil_commerce_logistics_document` | `number` (primary key), `kind` (`delivery_note`, `picking_slip`), `order_number` (indexed), `shipment` (empty for a picking slip), `issued_at`; unique by kind, order and shipment |
| `phil_commerce_logistics_wave` | `code` (primary key), `name`, `estimated_on` (date), `released_at`; indexed by release and day |
| `phil_commerce_logistics_wave_order` | `order_number` (primary key), `wave` (indexed) |

A reservation also keeps how many of its units already left, in its `shipped` column.
