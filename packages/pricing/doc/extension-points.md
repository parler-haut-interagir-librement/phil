# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. It computes; what to price,
which discounts apply and which endings a store uses are the application's, and reach the library as arguments.

| Point | How |
|---|---|
| Rounding | Every method that divides takes a `\RoundingMode` as its last argument — see [Rounding and limits](rounding.md) |
| Target margins, markups and discounts | `Rate` values the application builds from its settings with `Rate::of()`, `ofPercent()` or `ofBasisPoints()` |
| Which discounts apply, in which order | The list given to `DiscountCascade::apply()`; a promotion engine decides it, the cascade keeps its order |
| Price endings | `PriceEnding` for one rule, `PriceEndingBands` for rules by price band, or a class of the application's own implementing `Phil\Pricing\Contract\PriceEndingRule` — a table of hand-picked prices, for instance; bands accept any `PriceEndingRule` |
| Floors | The `$floor` of `PriceEndingRule::apply()`: the cost, or a price from `PriceCalculator` at the lowest accepted margin |
| Suggested prices | `SuggestedRetailPrice` from the supplier's list, a cost markup, keystone or a wholesale price; the application keeps the result, on a product for instance |
| Quantity tiers | `WholesalePriceList::fromMarkup()` and `from()`, added in ascending order of their quantities |
| Lines of a report | `PricedLine::of()` from the application's own sales; `MarginCalculator::summarize()` adds them |
| Catching the library's errors | Every exception implements `Phil\Pricing\Exception\PricingException`: one `catch` covers `InvalidRate`, `InvalidPricing`, `UndefinedRate` and `AmountOutOfRange`; `CurrencyMismatch` and `InvalidAmount` come from `phil/money` |

`PriceEndingRule` is stable within a minor version: an implementation receives a price and an optional floor and
returns a price in the same currency, never negative and never below the floor.
