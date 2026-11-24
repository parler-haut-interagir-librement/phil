# Price endings

A price computed from a cost rarely ends well: 9.24, 13.87. A `PriceEnding` moves it to a price that ends the way a
shelf shows it.

## Steps and endings

An ending is a step and an offset: the prices it allows repeat every step, at the offset.

| Step | Ending | Allowed prices |
|---|---|---|
| 1.00 | 0.99 | 0.99, 1.99, 2.99... |
| 1.00 | 0.95 | 0.95, 1.95, 2.95... |
| 1.00 | 0.00 | the whole units |
| 10.00 | 9.99 | 9.99, 19.99, 29.99... |
| 0.50 | 0.49 | 0.49, 0.99, 1.49, 1.99... |
| 100 (yen) | 80 | 80, 180, 280... |

```php
$ninetyNine = PriceEnding::ofDecimals('1', '0.99', $cad);
// or PriceEnding::of(Money::ofDecimal('1', $cad), Money::ofDecimal('0.99', $cad))

$ninetyNine->apply(Money::ofDecimal('9.24', $cad))->toDecimal(); // "9.99"
```

The step is above zero and the ending from zero to below the step, in the same currency; otherwise `InvalidPricing`
or `CurrencyMismatch` is raised.

## Up, nearest or down

The third argument is a `\RoundingMode`:

| Mode | Behaviour | 19.37 with .99 |
|---|---|---|
| `PositiveInfinity` (the default) | Up: the first allowed price at or above | 19.99 |
| `HalfAwayFromZero` | The nearest; a tie goes up | 18.99 |
| `NegativeInfinity` | Down: the last allowed price at or below | 18.99 |

Up is the default because a price computed from a cost should not lose margin to its ending.

## The floor

`apply($price, $floor)` never returns a price below the floor, nor below zero. When the mode would go below, the first
allowed price at or above the floor is taken. The floor is typically the cost, or the price for the lowest margin the
application accepts:

```php
$nearest = PriceEnding::ofDecimals('1', '0.99', $cad, RoundingMode::HalfAwayFromZero);

$nearest->apply(Money::ofDecimal('19.37', $cad))->toDecimal();                                // "18.99"
$nearest->apply(Money::ofDecimal('19.37', $cad), Money::ofDecimal('19.20', $cad))->toDecimal(); // "19.99"
```

A zero price ends on the first allowed price, 0.99 with the rule above: an application keeps free items out of the
rule. A negative price raises `InvalidPricing`: a refund negates the ended price rather than ending a negative one.

## Bands

`PriceEndingBands` picks the ending by price band:

```php
$endings = PriceEndingBands::startingWith(PriceEnding::ofDecimals('1', '0.99', $cad))
    ->from(Money::ofDecimal('10', $cad), PriceEnding::ofDecimals('1', '0.95', $cad))
    ->from(Money::ofDecimal('100', $cad), PriceEnding::ofDecimals('1', '0', $cad));

$endings->apply(Money::ofDecimal('9.24', $cad));   // 9.99
$endings->apply(Money::ofDecimal('13.87', $cad));  // 13.95
$endings->apply(Money::ofDecimal('123.45', $cad)); // 124.00
```

The first rule covers every price below the first threshold. Bands are added in ascending order of their thresholds,
each above zero and above the previous one; `from()` returns a new set and leaves the original untouched. The band is
chosen by the price before it is ended, and its rule ends it even when the result crosses into the next band.

Both `PriceEnding` and `PriceEndingBands` implement `PriceEndingRule`, so a band may hold another set of bands, or a
rule of the application's own.

A suggested retail price is ended the same way, by `SuggestedRetailPrice::ended()`: see
[Suggested retail and wholesale prices](suggested-retail-prices.md). Keeping it on a product is a catalogue's concern.
