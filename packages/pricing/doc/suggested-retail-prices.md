# Suggested retail and wholesale prices

A supplier sells to a retailer at a wholesale price and often suggests the price the product should sell at: the
suggested retail price (MSRP). `SuggestedRetailPrice` holds one, with where it comes from; `RetailPriceComparison`
tells how far a selling price is from it; `WholesalePriceList` prices a product for retailers, with quantity tiers.

Every amount is a `Money` from `phil/money`, every markup a `Rate` in parts per million, and every division rounds by
a named `\RoundingMode`.

## Where a suggested price comes from

| Constructor | Source | Price |
|---|---|---|
| `fromSupplier($price)` | `Supplier` | The price the supplier gives |
| `fromCost($cost, $markup)` | `CostMarkup` | `cost × (1 + markup)` |
| `keystone($cost)` | `CostMarkup` | Twice the cost: a markup of 100 % |
| `fromWholesale($wholesale, $markup)` | `WholesaleMarkup` | `wholesale × (1 + markup)` |

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\Rate;
use Phil\Pricing\SuggestedRetailPrice;

$cad = Currency::of('CAD');
$cost = Money::ofDecimal('6.00', $cad);

SuggestedRetailPrice::fromSupplier(Money::ofDecimal('24.99', $cad))->price->toDecimal();  // "24.99"
SuggestedRetailPrice::fromCost($cost, Rate::ofPercent('60'))->price->toDecimal();          // "9.60"
SuggestedRetailPrice::keystone($cost)->price->toDecimal();                                  // "12.00"
SuggestedRetailPrice::keystone($cost)->isKeystone();                                        // true
SuggestedRetailPrice::fromWholesale(Money::ofDecimal('7.80', $cad), Rate::ofPercent('100'))
    ->price->toDecimal();                                                                   // "15.60"
```

A suggested price keeps its `source`, its `basis` — the cost or the wholesale price it marks up, null for a supplier's
price — and its `markup`, null for a supplier's price. `isKeystone()` is true for a cost markup of exactly 100 %.

A price from a markup rounds away from zero by default, as [target prices](target-prices.md) do, so the markup is
always reached: a cost of 6.37 at 35 % is 8.5995, suggested at 8.60. Any `\RoundingMode` may be given as the last
argument.

## Ending a suggested price

`ended()` applies any [`PriceEndingRule`](price-endings.md) and returns a new suggested price; the price before the
ending stays in `unendedPrice`:

```php
use Phil\Pricing\PriceEnding;

$suggested = SuggestedRetailPrice::fromWholesale(Money::ofDecimal('7.80', $cad), Rate::ofPercent('100'))
    ->ended(PriceEnding::ofDecimals('1', '0.99', $cad));

$suggested->price->toDecimal();        // "15.99"
$suggested->unendedPrice->toDecimal(); // "15.60"
```

The ending never goes below the floor given as the second argument; without one, below the cost or the wholesale
price the suggested price came from. Ending an ended price starts again from `unendedPrice`. A rule that ends the
price at zero raises `InvalidPricing`: a suggested price is always above zero.

## Comparing a selling price

`compare()` returns a `RetailPriceComparison`: the difference in money, negative below the suggested price, and the
rate of that difference over the suggested price.

```php
$comparison = SuggestedRetailPrice::fromSupplier(Money::ofDecimal('25.00', $cad))
    ->compare(Money::ofDecimal('20.00', $cad));

$comparison->difference()->toDecimal(); // "-5.00"
$comparison->rate()->percent();         // "-20"
$comparison->rateBelow()->percent();    // "20": 20 % below the suggested price
$comparison->isBelow();                 // true
```

`rate()` is signed and rounds half away from zero unless a `\RoundingMode` is given: 12.79 against 15.99 is
`"-20.0125"`. `rateBelow()` is the same rate as a positive number below the suggested price, and zero at or above it.
`isBelow()`, `isAbove()` and `isAtSuggestedPrice()` tell the side. `RetailPriceComparison::of($selling, $suggested)`
compares two amounts without a `SuggestedRetailPrice`.

## Wholesale prices and quantity tiers

`WholesalePriceList::fromCost()` marks a cost up to a wholesale price, the tier from one unit. `fromMarkup()` adds a
tier priced from the cost by a lower markup, `from()` a tier at a given unit price; each returns a new list:

```php
use Phil\Pricing\WholesalePriceList;

$wholesale = WholesalePriceList::fromCost($cost, Rate::ofPercent('30')) // 7.80 a unit
    ->fromMarkup(12, Rate::ofPercent('25'))                               // 7.50 a unit from 12
    ->from(48, Money::ofDecimal('7.20', $cad));                           // 7.20 a unit from 48

$wholesale->basePrice()->toDecimal();      // "7.80"
$wholesale->unitPriceFor(11)->toDecimal(); // "7.80"
$wholesale->unitPriceFor(12)->toDecimal(); // "7.50"
$wholesale->unitPriceFor(47)->toDecimal(); // "7.50"
$wholesale->unitPriceFor(48)->toDecimal(); // "7.20"
$wholesale->totalFor(24)->toDecimal();     // "180.00"
```

An order reaches the last tier whose quantity it meets; `tierFor()` returns that `WholesaleTier`, with its
`minimumQuantity`, `unitPrice` and `markup`, null for a tier at a given price. Tiers are added in ascending order of
their quantities, each strictly above the previous one, and a tier never raises the unit price: a tier at the same
price as the previous one is accepted, a higher one raises `InvalidPricing`. A quantity below 1 raises
`InvalidPricing`.

A suggested retail price is often keystone on the wholesale price: `SuggestedRetailPrice::fromWholesale(
$wholesale->basePrice(), Rate::ofPercent('100'))`.

## Refused cases

| Case | Exception |
|---|---|
| A cost or wholesale price of zero to mark up | `InvalidPricing` |
| A negative cost or wholesale price | `InvalidPricing`: a refund negates the price, not the cost |
| A markup of -100 % or less | `InvalidRate` |
| A supplier's price, or a price after rounding or ending, not above zero | `InvalidPricing` |
| A negative selling price compared to a suggested price | `InvalidPricing`: the signs are opposite |
| A selling price, a floor, an ending or a tier price in another currency | `Phil\Money\Exception\CurrencyMismatch` |
| A tier whose quantity is not above the previous one, or whose price is higher | `InvalidPricing` |
| A tier price not above zero, or a quantity below 1 | `InvalidPricing` |
| A cost times a markup beyond the native integer range | `AmountOutOfRange` |

## What the library leaves to the catalogue

The library computes a suggested price and compares a selling price to it; it keeps nothing. Keeping the suggested
price on a product, with its source, belongs to `phil/commerce-catalog-bundle`, which will store it. A minimum
advertised price, a floor a supplier sets on the advertised price, is not modelled.
