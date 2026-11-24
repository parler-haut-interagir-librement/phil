# Rounding and limits

## Every rounding

Every division of the library is in integer arithmetic and says how it rounds, with PHP's `\RoundingMode`. Each
method that divides takes the mode as its last argument; the defaults are:

| Operation | Default | Why |
|---|---|---|
| Margin rate, markup, conversions, summaries | `HalfAwayFromZero` | The nearest part per million; a refund has the rates of its sale |
| Target prices | `AwayFromZero` | Up on a positive cost: the target is always reached |
| Suggested and wholesale prices from a markup | `AwayFromZero` | Up: the markup is always reached |
| Rate of a selling price against a suggested price | `HalfAwayFromZero` | The nearest part per million |
| Each discount of a cascade, effective rate | `HalfAwayFromZero` | The nearest unit, as a receipt shows it; a refund is the exact negation |
| Price endings | `PositiveInfinity` | Up: an ending never costs margin |

All eight modes of `\RoundingMode` are supported: `HalfAwayFromZero`, `HalfTowardsZero`, `HalfEven`, `HalfOdd`,
`TowardsZero`, `AwayFromZero`, `PositiveInfinity` and `NegativeInfinity`, each giving the result `round()` gives on the
exact quotient.

## Signs

| Amount | Accepted |
|---|---|
| Price and cost | Of the same sign, or either zero: a refund negates both. Opposite signs raise `InvalidPricing` |
| Quantity of a line | Any integer; a negative quantity is a return |
| Price given to a cascade | Any sign; a negated price gives the negated steps |
| Price given to an ending | Zero or more; a negative price raises `InvalidPricing` |
| Cost or wholesale price marked up to a suggested or wholesale price | Above zero; zero or negative raises `InvalidPricing` |
| Suggested price, wholesale unit price | Above zero, after rounding and ending; otherwise `InvalidPricing` |
| Selling price compared to a suggested price | Zero or more; a negative one raises `InvalidPricing` |

## Currencies

Every amount of one operation is in one currency. Two currencies meeting raise
`Phil\Money\Exception\CurrencyMismatch` from `phil/money`; the library never converts.

## The largest amounts

The library multiplies before it divides, in native integers. A rate multiplies an amount by one million: amounts up
to `PHP_INT_MAX / 1 000 000` minor units — about 92 billion in a two-decimal currency — are always computed. Beyond
that, a product that leaves the native range raises `AmountOutOfRange` rather than a wrong or float result. A result
that `Money` cannot hold raises `Phil\Money\Exception\InvalidAmount`.
