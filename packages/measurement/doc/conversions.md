# Conversions

## Exact values

A `Measurement` is a value and a unit. The value is read from a decimal string of 18 significant digits at most —
`'12.5'`, `'-0.25'`, `'1200'` — and kept as a fraction of two native integers, never as a float:

```php
$weight = $units->measurement('2.75', '[lb_av]');
$weight = $units->parse('2.75 [lb_av]');            // as toString() writes it
```

A conversion multiplies and divides the fraction by the factors of the two units, which are fractions too: a chain of
conversions keeps every digit in between, and the value is rounded once, when it is written.

```php
$weight->convertTo($units->unit('kg'))
    ->convertTo($units->unit('[oz_av]'))
    ->convertTo($units->unit('[lb_av]'))
    ->exactDecimal();                                // "2.75"
```

When the exact fraction of a step no longer fits in native integers — a pressure in pounds per square inch, whose
factor has fourteen digits, times a value of six decimals —, that step is rounded to 18 significant digits, or to 18
digits after the point for a value below one, through integer arithmetic only. A value whose integer part does not fit
in a native integer (about 9.2 × 10¹⁸) is refused with `MeasurementOutOfRange`.

## Writing a value

| Method | Gives |
|---|---|
| `toDecimal(3)` | The value rounded to 3 digits after the point at most, trailing zeros removed: `0.454`. |
| `toFixedDecimal(2)` | Exactly 2 digits after the point: `2.50`. |
| `exactDecimal()` | The exact decimal, or `null` when it never ends: one kilogram in pounds. |
| `round(2)` | A measurement rounded to 2 digits, in its unit. |
| `toString()` | `<decimal> <code>`, exact up to 18 significant digits, which `UnitRegistry::parse()` reads back. |

Each takes a `RoundingMode`, half to even by default.

## Arithmetic

Measurements of a family add, subtract, compare and multiply by a number, in any of its units; the result is in the
unit of the left-hand measurement:

```php
$units->measurement('1.5', 'kg')->add($units->measurement('250', 'g'));     // 1.75 kg
$units->measurement('1.5', 'kg')->multiply('1/3');                          // 0.5 kg
$units->measurement('1', '[lb_av]')->compareTo($units->measurement('16', '[oz_av]'));   // 0
```

Measurements of two families never mix: `IncompatibleUnits` is thrown.

## Temperatures and their differences

A temperature scale has an offset: 0 °C is 273.15 K. A temperature is therefore not a quantity to add, and a
difference of temperatures is measured in a family of its own, `temperature-difference`, without the offset:

```php
$morning = $units->measurement('10', 'Cel');
$morning->add($units->measurement('9', '[degF]{difference}'));        // 15 Cel
$units->measurement('68', '[degF]')->subtract($morning);              // 18 [degF]{difference}
$morning->add($morning);                                              // IncompatibleUnits
```

A temperature takes a difference, and a temperature minus a temperature is a difference. A temperature is neither
negated nor multiplied.

## Reciprocal units

Fuel consumption is written in litres per 100 km in most of the world and in miles per gallon in the United States:
one goes up as the other goes down. A reciprocal unit converts as `standard = factor ÷ value`, and a comparison reads
the standard unit, so that 30 miles per gallon is above 40, as it burns more:

```php
$units->measurement('30', '[mi_i]/[gal_us]')->convertTo($units->unit('L/(100.km)'))->toDecimal(2);   // "7.84"
```

Zero has no counterpart in a reciprocal unit and is refused. A family with a reciprocal unit converts and compares, but
its measurements are not added.

## Splitting over several units

A height is written in feet and inches, a person's weight in stones and pounds:

```php
$units->measurement('70.5', '[in_i]')->split($units->unit('[ft_i]'), $units->unit('[in_i]'));
// [5 [ft_i], 10.5 [in_i]]

$units->measurement('179.9', 'cm')->splitRounded([$units->unit('[ft_i]'), $units->unit('[in_i]')], 0);
// [5 [ft_i], 11 [in_i]]
```

Every part but the last is whole; the last takes what remains, exactly. `splitRounded()` rounds the measurement to the
digits of its last unit first, so that the parts never show 12 inches.
