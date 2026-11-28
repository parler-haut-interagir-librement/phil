# Price, stock and cost

## The price

A `PricePolicy` is one of two:

| Policy | Price |
|---|---|
| `PricePolicy::fixed(Money $price)` | The price given, above zero, whatever the components sell for apart. |
| `PricePolicy::discountOnComponents(Rate $discount, RoundingMode $rounding)` | The sum of the components' prices less the rate, from 0 to 99.9999 %. |

A discount is a phil/pricing `Rate` in parts per million — `Rate::ofPercent('15')` is 150,000 — and the discount is
rounded to the unit by the `\RoundingMode` given, half away from zero by default. The same components always give the
same price.

`BundlePricing::quote()` reads each component's unit price through `Contract\ComponentPrices` and gives a
`BundleQuote`:

| Field | What |
|---|---|
| `componentsValue` | What the components sell for apart: each unit price times its units. |
| `price` | What the bundle sells for. |
| `components` | A `QuotedComponent` each: unit price, value, and share of the price. |
| `savings()` | The value less the price; negative when the bundle sells above its components. |

The shares follow the components' values: a book of 40.00 and dice of 10.00 in a bundle of 45.00 take 36.00 and
9.00. They are split by `Money::allocate()`, so they add up to the price to the unit; when every component is free
apart, they follow the quantities. A component without a price in the bundle's currency, in another currency, or below
zero throws `PriceUnknown`: the bundle is not sold at a guess.

## The stock

`BundleAvailability::of()` reads each component's available units through `Contract\ComponentStock`, divides them by
the units one bundle holds, rounded down, and keeps the smallest: a `BundleStock` with the quantity and the component
that sets it, the first one on a tie. A component at zero or below gives zero bundles.

## The cost

`ComposedCost::of()` reads each component's unit cost through `Contract\ComponentCosts` and gives a `BundleCost`: each
component's unit cost and its cost for one bundle, and their sum. When a component's cost is unknown, the sum is null
and `unknown` lists the components at fault: a cost that leaves out a component would be too low.

The bundle has no cost of its own, as it has no stock of its own: its cost is always composed from its components'
costs now, by the method the stock valuation uses — weighted average or first in, first out.

## The margin

`ComposedCost::margin()` takes a quote and a cost and gives a `BundleMargin`: the price, the cost, the margin and its
rate over the price, rounded half away from zero unless another `\RoundingMode` is given. It is null when the cost is
unknown or in another currency than the price; its rate is null when the price is zero.

`Bundles::margin()` does both for a SKU.
