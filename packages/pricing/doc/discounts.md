# Discounts

`DiscountCascade::apply()` takes a price and a list of discount rates, and applies each to what the previous one left,
in the order given:

```php
$sale = new DiscountCascade()->apply(Money::ofDecimal('100.00', $cad), [Rate::ofPercent('20'), Rate::ofPercent('10')]);

$sale->steps[0]->discount->toDecimal(); // "20.00", on 100.00
$sale->steps[1]->discount->toDecimal(); // "8.00", on 80.00
$sale->price->toDecimal();              // "72.00"
$sale->totalDiscount()->toDecimal();    // "28.00"
$sale->effectiveRate()->percent();      // "28", not 30
```

## The result

`apply()` returns a `DiscountedPrice`:

| Member | What it holds |
|---|---|
| `original` | The price given |
| `steps` | One `DiscountStep` per rate, in the order given |
| `price` | The price after the last step |
| `totalDiscount()` | The original price less the final one: the sum of the step discounts, exactly |
| `effectiveRate()` | The total discount over the original price, rounded half away from zero unless a `\RoundingMode` is given |

Each `DiscountStep` holds its `rate`, the price `before` it, the `discount` it took off and the price `after` it. Each
step starts from the `after` of the previous one; a receipt can print each discount on its own line.

## Rounding

Each step rounds its own discount to the unit, half away from zero by default, and the rounding is passed as the third
argument. Rounding each step is what a receipt shows; the final price may differ by a unit from one computed on the
combined rate. The order of the rates is the application's decision and is kept: with rounding, two orders can give
prices a unit apart.

A negated price — a refund — gives exactly the negated steps.

## What is refused

A rate below 0 % or above 100 % raises `InvalidRate`: a surcharge is not a discount. 100 % takes the whole price, and
the next steps take nothing. An empty list leaves the price as it is. The effective rate of a zero price is undefined
and raises `UndefinedRate`.

Fixed-amount discounts, stacking rules and conditions are a promotion engine's work; the cascade only chains rates.
