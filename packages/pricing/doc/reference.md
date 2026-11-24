# Reference

Every type of the library, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Pricing\Rate` | `final readonly class` | Parts per million; read from a percentage or basis points, printed as a percentage, compared |
| `Phil\Pricing\MarginCalculator` | `final readonly class` | Margin, margin rate and markup of a price and a cost; conversions both ways; summary of many lines |
| `Phil\Pricing\PricedLine` | `final readonly class` | A unit price, a unit cost and a quantity; revenue, total cost and margin |
| `Phil\Pricing\MarginSummary` | `final readonly class` | Revenue, cost and margin of one or more lines; their margin rate and markup |
| `Phil\Pricing\PriceCalculator` | `final readonly class` | The price for a target margin, markup or cost share |
| `Phil\Pricing\DiscountCascade` | `final readonly class` | Successive discount rates, each on what the previous one left |
| `Phil\Pricing\DiscountedPrice` | `final readonly class` | The original price, the steps and the final price; total discount and effective rate |
| `Phil\Pricing\DiscountStep` | `final readonly class` | One discount: its rate, the price before, the discount and the price after |
| `Phil\Pricing\Contract\PriceEndingRule` | interface | Ends a price, never below zero or a floor |
| `Phil\Pricing\PriceEnding` | `final readonly class`, `PriceEndingRule` | A step, an ending and a `\RoundingMode`: .99, .95, whole units... |
| `Phil\Pricing\PriceEndingBands` | `final readonly class`, `PriceEndingRule` | One rule per price band, chosen by the price before it is ended |
| `Phil\Pricing\SuggestedRetailPrice` | `final readonly class` | A suggested retail price above zero, with its source, basis and markup; ended, compared |
| `Phil\Pricing\SuggestedRetailPriceSource` | backed `enum` | `Supplier`, `CostMarkup` or `WholesaleMarkup` |
| `Phil\Pricing\RetailPriceComparison` | `final readonly class` | A selling price against a suggested price: difference, signed rate, rate below |
| `Phil\Pricing\WholesalePriceList` | `final readonly class` | A wholesale price from a cost and a markup, with quantity tiers |
| `Phil\Pricing\WholesaleTier` | `final readonly class` | One tier: from how many units, the unit price, the markup that gave it |
| `Phil\Pricing\Exception\PricingException` | interface, extends `Throwable` | Every exception of the library |
| `Phil\Pricing\Exception\InvalidRate` | `final class`, `InvalidArgumentException` | A percentage malformed, or a rate with no meaning where it is given |
| `Phil\Pricing\Exception\InvalidPricing` | `final class`, `DomainException` | Opposite signs, a negative price to end, an invalid ending, bands out of order, a cost not above zero to mark up, tiers out of order or raising the price |
| `Phil\Pricing\Exception\UndefinedRate` | `final class`, `DomainException` | A rate asked of a zero price, cost or original price |
| `Phil\Pricing\Exception\AmountOutOfRange` | `final class`, `DomainException` | An intermediate product beyond the native integer range |

`Phil\Pricing\IntegerMath` is internal: the integer division by a `\RoundingMode` and the checked products the other
types share. `Phil\Pricing\MarkedUpPrice` is internal too: the markup of a cost above zero that suggested and wholesale
prices share.

See [margins.md](margins.md), [target-prices.md](target-prices.md), [discounts.md](discounts.md),
[price-endings.md](price-endings.md), [suggested-retail-prices.md](suggested-retail-prices.md) and
[rounding.md](rounding.md).
