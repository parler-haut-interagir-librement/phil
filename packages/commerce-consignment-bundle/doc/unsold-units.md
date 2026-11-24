# Unsold units

A consignor's units that did not sell either go back to the consignor, or are bought by the shop, as their
[agreement](agreements.md) says. `UnsoldStock` lists the units due and does either, in the ledger and in the stock.

## Units due

```php
foreach ($unsold->due($clock->now()) as $units) {
    $units->consignor;  // Owner
    $units->sku;
    $units->location;
    $units->units;      // what the layer still holds
    $units->dueAt;      // the end of the term, extensions included
    $units->action;     // UnsoldPolicy::ReturnToConsignor or UnsoldPolicy::BuyOut
}
```

Each consigned layer that still holds units is due when the agreement in effect at its receipt has a term, over by
then. The list goes from the first due to the last, of every consignor or of one, at most `$limit` layers — 500 by
default —, read from the 5 000 oldest consigned layers. A layer moved to another location keeps its receipt's
instant, and so its due date.

## Giving units back

```php
$slip = $unsold->giveBack('RS-2026-014', Owner::consignor('author-17'), 'store', [
    'BOOK-123' => 3,
    'ZINE-7' => 2,
], $clock->now());
$entityManager->flush();
```

A return slip takes, SKU by SKU, the consignor's units at the location from their layers, the oldest receipt first —
the first due —, and writes a `given_back` entry for each layer, under the slip's reference. Then it takes the units
off the shelf through the `StockMovements` port. The `ReturnSlip` it returns lists the layers and units, for the slip
the consignor signs.

Everything is checked before a unit moves: more units than the consignor has at the location in the ledger, or than
the shelf holds, refuses the whole slip, and neither the ledger nor the stock change. A slip names 1 to 500 SKUs. Units
not due yet may be given back too: a consignor who asks for their units early gets them.

## The shop buys the units

```php
$slip = $unsold->buyOut('BO-2026-003', Owner::consignor('author-17'), 'BOOK-123', 'store', 2, unitCost: null, at: $clock->now());
$slip->owed();  // what the shop owes the consignor for them
```

A buy-out changes the owner of the units, not their place: they leave the consignor's layers, the oldest first, and
each part reaches a new layer of the shop's own at the location, at the agreed unit cost, received at the instant of
the buy-out. Nothing moves in the stock.

The agreed cost is the `unitCost` given, or, when it is `null`, the consignor's share on layers with a share: a share
is already a price agreed per unit. Units left on a commission have no price before they sell: their buy-out needs a
`unitCost`, and is refused without one before a unit moves.

The consignor is owed the cost as for a sale: the `bought_out` entry carries it as its revenue and as the consignor's
share, with no commission. It appears on the consignor's next [statement](statements.md) and in their
[balance](payouts.md#the-balance). Later sales of these units are the shop's, at the margin of their new cost.

## With the logistics module

`LogisticsStockMovements` takes the units given back off the shelf through `Stock::adjust()`, under the slip's
reference, after checking the level has them on hand. See [With the logistics module](logistics.md#how-the-stock-moves).
