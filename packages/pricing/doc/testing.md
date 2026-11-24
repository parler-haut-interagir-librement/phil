# Testing

## The library's tests

`tests/Unit` holds:

- `IntegerMathTest`: each of the eight `\RoundingMode`s on both sides of the half, on exact halves, on both signs and
  on a negative denominator, checked against `round()` on two thousand generated quotients; the half found without
  overflow on the largest denominator; products and differences out of range refused.
- `RateTest`: percentages read and printed back, malformed ones refused, basis points, comparisons.
- `MarginCalculatorTest`: margins, margin rates and markups of sales, refunds and sales below cost; zero prices and
  costs; opposite signs and currencies refused; both conversions and their refused bounds; summaries weighed by their
  amounts.
- `PriceCalculatorTest`: prices for a margin, a markup and a cost share, rounded away from zero, in three currencies,
  with their refused targets and the largest cost priced.
- `DiscountCascadeTest`: chained steps, kept order, per-step rounding, refunds, bounds, effective rates.
- `PriceEndingTest`: endings up, to the nearest and down, floors, currencies, bands and their order.
- `SuggestedRetailPriceTest`: the three sources and keystone, their roundings, endings that keep the unended price
  and never go below the basis or the floor, the refused costs, markups and prices.
- `RetailPriceComparisonTest`: differences and rates below, at and above the suggested price, their roundings, the
  refused currencies and signs.
- `WholesalePriceListTest`: the base tier, tiers by markup and by price, the tier each quantity reaches, totals, tiers
  out of order or raising the price, the refused costs, markups and quantities.
- `SuggestedRetailPriceDocumentationTest`: every example of [Suggested retail and wholesale prices](suggested-retail-prices.md)
  with the output it shows.
- `PropertiesTest`: thousands of generated cases from a fixed seed — the conversions both ways within their bounds,
  each target price the smallest that reaches its target, cascades that chain exactly and stay within half a unit per
  step of the exact result, refunds that negate the sale, ended prices allowed, never below the price or the floor and
  less than a step above; suggested prices the smallest that reach their markup, ended suggested prices never below
  the unended one when the rule rounds up, comparisons that add up, wholesale unit prices that never rise with the
  quantity.
- `ExceptionsTest`: every exception under `PricingException`.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/pricing/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Mutation testing holds the library to the bar of a domain layer: every mutant of `src/` is killed.

## Testing code that prices

- **Assert on minor units and parts per million**: `self::assertSame(924, $price->minorUnits)`,
  `self::assertSame(400_000, $rate->partsPerMillion)`.
- **Keep a case that does not divide evenly**, such as a 35 % margin on 6.00: a computation that rounds to the
  nearest instead of up gives 9.23 and fails it.
- **Keep a refund case**: the negated price and cost give the same rates and the negated amounts.
- The library has no clock, container or I/O: no double is needed.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure a summary of one to two thousand lines, a cascade of three
discounts on each line, a price for a margin ended by bands on each cost, and a wholesale price list with a tier, a
suggested price ended by bands and its comparison on each cost. From the monorepo root:

```sh
composer bench -- packages/pricing/tests/Benchmark
```
