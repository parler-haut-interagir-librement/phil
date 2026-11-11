# Allocation

Splitting an amount must not lose or create a unit: three people sharing 1.00 pay 0.34, 0.33 and 0.33, and the
parts add up to 1.00 again.

## `allocate(int ...$ratios): list<Money>`

Splits the amount by ratios. Each part receives its share rounded down; the units left over go one by one to the
parts whose share was rounded down the most — the **largest remainder** —, the earliest part first on a tie.

```php
$cad = Currency::of('CAD');

Money::of(100, $cad)->allocate(1, 1, 1);  // 34, 33, 33
Money::of(100, $cad)->allocate(1, 1, 5);  // 14, 14, 72: 71.43 was rounded down the most
Money::of(5, $cad)->allocate(3, 7);       // 2, 3: a tie goes to the earlier part
Money::of(5, $cad)->allocate(0, 1, 1);    // 0, 3, 2: a ratio of 0 receives nothing
Money::of(-100, $cad)->allocate(1, 1, 1); // -34, -33, -33
```

A ratio is a whole number, zero or more; at least one ratio is given, and they do not all add up to zero. A
percentage split is written in whole ratios: `allocate(70, 30)`, or `allocate(9_975, 5_000)` for rates with
decimals. The result is a list, whatever the keys of the ratios.

## `split(int $parts): list<Money>`

Splits the amount into equal parts, the earliest parts receiving the units left over.

```php
Money::of(100, $cad)->split(3);  // 34, 33, 33
Money::of(11, $cad)->split(3);   // 4, 4, 3
Money::of(-100, $cad)->split(3); // -34, -33, -33
```

## Refusals

All `InvalidAmount`: no ratio; a negative ratio; ratios that add up to zero; ratios, or the amount times a ratio,
outside the native integer; fewer than one part.
