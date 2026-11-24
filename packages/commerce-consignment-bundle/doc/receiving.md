# Receiving goods

A `Receipt` is goods that came in at one location under one reference — a delivery note, a consignment slip —, with
1 to 500 lines. Each line is either bought or consigned, and one receipt may hold both for the same SKU:

```php
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;

$receipt = Receipt::of('DN-1042', 'store', [
    // Bought: the unit cost the shop paid.
    ReceiptLine::purchased('BOOK-123', 5, Money::of(1200, $cad)),
    // Consigned for a fixed share of each copy sold.
    ReceiptLine::consigned('BOOK-123', 5, 'author-17', ConsignmentTerms::share(Money::of(1500, $cad))),
    // Consigned for the shop's commission on the price paid: 30 % is 300 000 parts per million.
    ReceiptLine::consigned('MUG-4', 12, 'potter-3', ConsignmentTerms::commission(300_000)),
]);

$layers = $stock->receive($receipt, $clock->now());
```

| Value | Rule |
|---|---|
| SKU, location, consignor, reference | 1 to 64 characters, no control character, compared byte by byte |
| Quantity | One unit or more |
| Unit cost, share | Zero or more; the share is owed for each unit sold, whatever the price |
| Commission | 0 to 1 000 000 parts per million — the unit of `phil/pricing`'s rates —, an integer that never rounds a fraction away before the price is known |

`ConsignmentStock::receive()` opens one layer per line, writes a `received` entry for each, then adds the units to the
stock of the logistics module at the receipt's location. A refusal of the ledger moves no stock.

## Against a purchase order

`Receipt::of(..., purchaseOrder: 'PO-88')` receives the units against an open purchase order of the logistics module,
through its `Purchasing::receive()`: they count against what the order still expects, and never beyond it. The receipt
is made at the order's location; another location is refused before anything moves.

## Stock already on hand

`ConsignmentStock::adopt()` starts to keep the owners of units the logistics module holds already — stock received
before the bundle was installed —, without moving the stock: it writes `adopted` entries. The ledger never holds more
units of a SKU at a location than the logistics module has on hand there; an adoption beyond that is refused.
