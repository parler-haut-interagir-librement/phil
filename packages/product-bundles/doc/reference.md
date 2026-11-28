# Reference

Every public type of the package, with its role.

## Services

| Type | Role |
|---|---|
| `Bundles` | The bundles by SKU: quote, stock, cost, margin, and the line a sale freezes. |
| `BundlePricing` | Quotes a bundle from its components' prices and shares its price among them. |
| `BundleAvailability` | The bundles that can be sold, set by the scarcest component. |
| `ComposedCost` | The cost of a bundle composed from its components' costs, and its margin. |
| `Memory\InMemoryBundles` | The bundles in an array. |

## Values

| Type | Role |
|---|---|
| `ProductBundle` | A SKU over 1 to 50 components, a kind, a currency and a price policy; stored and read back. |
| `BundleComponent` | A component's SKU and the units one bundle holds. |
| `BundleKind` | `Assembled` or `Collection`; `taxedAsOne()`. |
| `PricePolicy` | A fixed price, or a discount on the components with its `\RoundingMode`. |
| `BundleQuote` | The components' value, the price, the shares; `savings()`. |
| `QuotedComponent` | A component of a quote: unit price, value, share. |
| `BundleStock` | The bundles that can be sold and the component that sets the limit. |
| `BundleCost` | The composed cost of one bundle, or the components whose cost is unknown. |
| `ComponentCost` | A component's unit cost and its cost for one bundle. |
| `BundleMargin` | Price, cost, margin and margin rate. |
| `SoldBundle` | A sale frozen on its line: price, shares, costs, quantities; draws and whole returns. |
| `SoldComponent` | A component as it was sold. |
| `StockDraw` | Units of a component to issue or put back. |
| `BundleRefund` | Whole bundles returned: amount, shares, units to put back, the line after. |
| `Cooperative\CooperativeBundle` | A bundle shared by sellers: weights, approvals, the lock, the charity, the shares. |
| `Cooperative\CooperativeShare` | A participant's part of a revenue, or the charity's. |

## Ports

| Type | Role |
|---|---|
| `Contract\BundleRepository` | Keeps the bundles. |
| `Contract\ComponentPrices` | A component's unit price in a currency. |
| `Contract\ComponentStock` | A component's available units. |
| `Contract\ComponentCosts` | A component's unit cost now. |

## Exceptions

| Type | Thrown when |
|---|---|
| `Exception\ProductBundlesException` | The interface of every exception of the package. |
| `Exception\InvalidBundle` | A SKU, a quantity, a setting or a stored row is out of bounds. |
| `Exception\UnknownBundle` | No bundle has the SKU. |
| `Exception\PriceUnknown` | A component has no price in the bundle's currency. |
| `Exception\SaleRefused` | The components cannot fill a sale. |
| `Exception\ReturnRefused` | A return asks for fewer than one bundle, or more than are left. |
| `Exception\CooperativeRefused` | A cooperative bundle refuses a change in its state. |
