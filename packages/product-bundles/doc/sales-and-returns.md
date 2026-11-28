# Sales and returns

## Selling

`Bundles::sell($sku, $quantity)` checks the stock, quotes the bundle, composes its cost and freezes all three in a
`SoldBundle`, from 1 to 1,000 bundles. When the components cannot fill the sale it throws `SaleRefused`, naming the
component that sets the limit; `backorder: true` sells anyway, for a shop that sells on back order.

`SoldBundle::of()` freezes a quote of the application's own, with or without a cost.

## The frozen line

| Field or method | What |
|---|---|
| `unitPrice`, `total()` | The price of one bundle, and of the quantity sold. |
| `components` | A `SoldComponent` each: units per bundle, share of one bundle's price, unit cost when known. |
| `shares()` | The total shared among the components, adding up to it: what a collection is taxed on. |
| `unitCost()` | What one bundle cost at the sale; null when a component's cost was unknown. |
| `draws()` | A `StockDraw` per component: the units the sale reserves or issues. |
| `quantity`, `returned`, `returnable()` | Sold, returned, and left to return. |

The line is stored with the order: `toArray()` gives scalars only, each unit cost with its own currency, and
`fromArray()` reads it back, refusing a row whose shares do not add up to the unit price.

## Taxes

An assembled bundle is taxed on `total()` by the bundle's own tax category. A collection is taxed on each of
`shares()` by its component's category, so a print book and its PDF are each taxed at their own rate.

## Returning

`SoldBundle::refund($bundles)` returns whole bundles, never a component alone:

| Field | What |
|---|---|
| `amount` | The unit price times the bundles returned: what was paid for them. |
| `shares` | The amount shared among the components, for a collection's taxes. |
| `restock` | A `StockDraw` per component: the units to put back. |
| `after` | The sold bundle with these bundles counted as returned; store it over the line. |

Fewer than one bundle, or more than are left to return, throw `ReturnRefused`. The units go back at the cost the stock
valuation recorded when they left; the library hands over the units, the logistics values them.
