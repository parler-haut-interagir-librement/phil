# Testing

## The library's tests

`tests/Unit` holds `TaxRateTest`, `TaxBreakdownTest` and `MultiRateTaxCalculatorTest`: rounding on both sides of
the half, on negated bases and in currencies of 0, 2 and 3 decimals; the largest base taxed and the next refused;
codes, shares and percentages accepted and refused, percentages read and printed back; breakdowns on one base,
refused on another base, in another currency or with a code twice; reported amounts; reversal line by line; taxes
taken out of a price, the base rounded on both sides of the half, a negated price, the largest price and the next
refused.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/tax/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Mutation testing holds the library to the bar of a domain layer: every mutant of `src/` is killed.

## Testing code that taxes

- **Assert on minor units**, line by line: `self::assertSame(199, $breakdown->line('QST')?->amount->minorUnits)`.
- **Keep a case whose lines round in opposite directions**, such as 19.99 at 5 % and 9.975 %: a computation that
  rounds the sum of the rates once, or compounds them, fails it.
- **Assert that a reversal is the exact negation**: `$credit->totalWithTax()->equals($invoice->totalWithTax()->negate())`.
- The library has no clock, container or I/O: no double is needed.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `calculate()` and `extract()` over carts of one to two hundred lines, with one to three rates. From the monorepo root:

```sh
composer bench -- packages/tax/tests/Benchmark
```
