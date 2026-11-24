# Margins and commissions

Each sale line carries a `Margin`, worked out by the `MarginCalculator` port:

| Owner | `revenue` | `cost` | `margin` |
|---|---|---|---|
| The shop | The price paid for the units | Their unit cost, times the units | The margin: revenue less cost |
| A consignor | The price paid for the units | The consignor's share: what the shop owes | The commission: revenue less share |

Every amount is a `Phil\Money\Money`, in whole minor units, never a float.

## The default calculator

`StandardMargins` is the default:

- the shop's units: the unit price times the units, less the unit cost times the units;
- a share: the share times the units is owed whatever the price paid, so a unit sold below its share gives a negative
  commission, which the ledger records as it is;
- a commission rate: the price of all the units is split at once with `Money::allocate()`, which never loses nor
  creates a cent — the shop's commission and the consignor's share always add up to the price paid. 30 % of 19.99 gives
  the shop 6.00 and the consignor 13.99.

The same units at the same price always give the same margin: a return is the sale's margin negated, at the price the
units were sold at.

## Another calculator

An application with rules of its own — through `phil/pricing`, a rounding of its own, a share that depends on the
price — implements `MarginCalculator` and aliases the interface to its service: see
[Extension points](extension-points.md).

## What a sale owes

`Sale::owedToConsignors()` adds, by consignor, the shares of a sale's consigned lines. The entries of the ledger keep
the same figures — `revenue` and `cost`, by consignor and instant —: the [statements](statements.md) and the
[balance](payouts.md#the-balance) of each consignor read them, and never work them out again from the sales.
