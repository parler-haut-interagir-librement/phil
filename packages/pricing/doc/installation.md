# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on; `\RoundingMode` comes from PHP 8.4 |
| `phil/money` | ^0.1 | `Money` and `Currency`, in which every price, cost and discount is counted, and `CurrencyMismatch` |

## Installing

```sh
composer require phil/pricing
```

The library registers nothing and reads no configuration. `MarginCalculator`, `PriceCalculator` and
`DiscountCascade` hold no state and take no constructor argument: construct them where needed, or declare them as
services. `PriceEnding` and `PriceEndingBands` are immutable values, built once from the application's settings and
safe to share between requests.
