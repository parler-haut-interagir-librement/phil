# Margins and markups

## Rates

A `Rate` is an integer number of parts per million: `1_000_000` is 100 %, `400_000` is 40 %, `99_750` is 9.975 %. It
is never a float.

| Constructor | Example | Parts per million |
|---|---|---|
| `Rate::of(int)` | `Rate::of(400_000)` | 400 000 |
| `Rate::ofPercent(string)` | `Rate::ofPercent('9.975')`, `Rate::ofPercent('-12.5')` | 99 750, -125 000 |
| `Rate::ofBasisPoints(int)` | `Rate::ofBasisPoints(125)` | 12 500 |
| `Rate::zero()` | | 0 |

`ofPercent()` reads digits with an optional leading `-` and up to four decimals; anything else — a `%` sign, a comma,
a `+`, a fifth decimal — raises `InvalidRate`. `percent()` prints the rate back without trailing zeros: `"40"`,
`"66.6667"`, `"-12.5"`. `equals()`, `compare()` and `isNegative()` compare rates.

A rate may be negative or above 100 %: a sale below cost has a negative margin, a markup of 150 % is common. Where a
rate has no meaning, the operation that takes it refuses it, never `Rate` itself.

## Margin, margin rate and markup

`MarginCalculator` takes a price and a cost:

| Method | Formula | 10.00 on a cost of 6.00 |
|---|---|---|
| `margin($price, $cost)` | price − cost | 4.00 |
| `marginRate($price, $cost)` | (price − cost) / price | 40 % |
| `markupRate($price, $cost)` | (price − cost) / cost | 66.6667 % |

The price is what the customer pays per unit before taxes and after discounts. The rates round half away from zero
by default; any `\RoundingMode` may be passed as the last argument:

```php
$margins = new MarginCalculator();

$margins->markupRate($price, $cost)->percent();                            // "66.6667"
$margins->markupRate($price, $cost, RoundingMode::TowardsZero)->percent(); // "66.6666"
```

The margin rate of a zero price and the markup on a zero cost are undefined and raise `UndefinedRate`; the library
never makes up a value for them. A zero cost has a margin rate of 100 %; a zero price on a positive cost has a markup
of -100 %.

## Converting a margin rate and a markup

| Method | Formula | Example |
|---|---|---|
| `markupFromMargin(Rate $margin)` | margin / (1 − margin) | 40 % → 66.6667 % |
| `marginFromMarkup(Rate $markup)` | markup / (1 + markup) | 100 % → 50 % |

A margin of 100 % or more leaves nothing for the cost and raises `InvalidRate`. A markup of -100 % is a zero price
and a markup below it a price of the other sign: both raise `InvalidRate`.

The conversions are exact within rounding. A margin from 0 to 100 % converted to a markup and back comes back
unchanged; a negative margin, or a markup from 0 to 100 % converted to a margin and back, comes back within two parts
per million. The property tests check these bounds on thousands of generated rates.

## Signs and refunds

A refund negates the price and the cost; its margin is negated and its rates are those of the sale. A price and a
cost of opposite signs — a positive price on a negative cost — describe no sale and raise `InvalidPricing`. Either
may be zero: a free item has a zero price, a donated item a zero cost.

## The margin of many lines

A `PricedLine` is a unit price, a unit cost and a quantity; its `revenue` and `totalCost` are both times the quantity,
and `margin()` their difference. A negative quantity is a return.

`MarginCalculator::summarize()` adds one or more lines into a `MarginSummary` of `revenue`, `cost` and `margin`, whose
`marginRate()` and `markupRate()` are computed on those totals:

```php
$summary = $margins->summarize(
    PricedLine::of(Money::ofDecimal('1.00', $cad), Money::ofDecimal('0.10', $cad)),
    PricedLine::of(Money::ofDecimal('100.00', $cad), Money::ofDecimal('90.00', $cad)),
);

$summary->marginRate()->percent(); // "10.7921", not "50", the average of 90 % and 10 %
```

Each line weighs by its amounts: the margin of a category is its total margin over its total revenue, never the
average of the rates of its products. `summarize()` takes at least one line; spread a list with `...$lines`. Lines in
different currencies raise `CurrencyMismatch`.
