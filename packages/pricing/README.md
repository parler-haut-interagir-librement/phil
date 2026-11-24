# phil/pricing

**Prices from costs, margins and discounts, exact to the unit, with no float anywhere.**

phil/pricing computes what a store prices with: the margin and the markup of a sale, the conversion of one rate to
the other, the price that reaches a target margin or markup, successive discounts applied one after the other, the
blended margin of many lines, the ending of a computed price on .99, .95 or a whole unit, suggested retail prices
and how far a selling price is from one, and wholesale prices with quantity tiers. It does not hold costs, decide
which discounts apply, or know a catalogue: those belong to the application, which passes amounts and rates in.

It is for any PHP code that sets prices from costs or reports margins: a catalogue import that prices every product
from its supplier cost, a point of sale that shows the margin of a cart, a report of the margin of a category.

It is a plain PHP library whose only dependency is `phil/money`, whose `Money` it takes and returns. Rates are held in
parts per million in a `Rate`, never in a float. Every division says how it rounds with PHP's `\RoundingMode`: rates
round half away from zero, target prices round away from zero so the target is always reached, endings round up by
default.

## Status

| Part | State |
|---|---|
| `Rate`: parts per million, read from and printed as a percentage, basis points | Implemented and tested |
| `MarginCalculator`: margin, margin rate, markup, conversions both ways, summary of many lines | Implemented and tested |
| `PricedLine` and `MarginSummary`: lines weighed by their amounts | Implemented and tested |
| `PriceCalculator`: price from a target margin, markup or cost share | Implemented and tested |
| `DiscountCascade`: successive discounts, every step exposed, effective rate | Implemented and tested |
| `PriceEnding` and `PriceEndingBands`: endings such as .99, up or to the nearest, never below a floor, by price band | Implemented and tested |
| `SuggestedRetailPrice`: from the supplier, a cost markup, keystone or a wholesale price; ended; compared to a selling price | Implemented and tested |
| `WholesalePriceList`: wholesale price from a cost and a markup, quantity tiers that never raise the price | Implemented and tested |
| Exceptions under one `PricingException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Margins and markups](doc/margins.md) | `Rate`, `MarginCalculator`, `PricedLine` and `MarginSummary`: the rates, their conversions, the blended margin. |
| [Target prices](doc/target-prices.md) | `PriceCalculator`: the price for a margin, a markup or a cost share, and why it rounds up. |
| [Discounts](doc/discounts.md) | `DiscountCascade`: the order, the rounding of each step, the effective rate. |
| [Price endings](doc/price-endings.md) | `PriceEnding` and `PriceEndingBands`: .99, .95, whole units, modes, floors, bands. |
| [Suggested retail and wholesale prices](doc/suggested-retail-prices.md) | `SuggestedRetailPrice`, `RetailPriceComparison` and `WholesalePriceList`: sources, keystone, endings, comparisons, quantity tiers. |
| [Rounding and limits](doc/rounding.md) | Every rounding of the library, the signs it accepts, and the largest amounts. |
| [Extension points](doc/extension-points.md) | What an application chooses, replaces or adds. |
| [Testing](doc/testing.md) | The library's tests, properties and benchmarks, and how to test code that prices. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/pricing
```

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\DiscountCascade;
use Phil\Pricing\MarginCalculator;
use Phil\Pricing\PriceCalculator;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\Rate;
use Phil\Pricing\SuggestedRetailPrice;

$cad = Currency::of('CAD');
$cost = Money::ofDecimal('6.00', $cad);

$margins = new MarginCalculator();
$margins->marginRate(Money::ofDecimal('10.00', $cad), $cost)->percent(); // "40"
$margins->markupRate(Money::ofDecimal('10.00', $cad), $cost)->percent(); // "66.6667"
$margins->markupFromMargin(Rate::ofPercent('40'))->percent();          // "66.6667"

$price = new PriceCalculator()->priceForMargin($cost, Rate::ofPercent('35')); // 9.24, never under 35 %
$shelf = PriceEnding::ofDecimals('1', '0.99', $cad)->apply($price, $cost);  // 9.99

$sale = new DiscountCascade()->apply($shelf, [Rate::ofPercent('20'), Rate::ofPercent('10')]);
$sale->price->toDecimal();                 // "7.19": 9.99 less 2.00, then less 0.80
$sale->effectiveRate()->percent();         // "28.028"

$suggested = SuggestedRetailPrice::keystone($cost)->ended(PriceEnding::ofDecimals('1', '0.99', $cad)); // 12.99
$suggested->compare($sale->price)->rateBelow()->percent(); // "44.6497": 44.65 % below the suggested price
```

## Requirements

PHP 8.5 or later, and `phil/money` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
