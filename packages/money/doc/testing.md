# Testing

## The library's tests

`tests/Unit` holds `CurrencyTest`, `MoneyTest` and `SchemaOrgTest`: known and refused codes, precision, localized
names and symbols; arithmetic and comparison in one currency and refused across two; every overflow, `PHP_INT_MIN`
included; decimals read and printed for currencies of 0, 2 and 3 digits, up to the largest amount; allocations with
remainders, ties, zero ratios and negative amounts; the schema.org `MonetaryAmount` and `Offer` price, decimals kept
as text.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/money/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Mutation testing holds the library to the bar of a domain layer: every mutant of `src/` is killed.

## Testing code that counts money

- **Assert on minor units**, not on decimals or formatted strings: `self::assertSame(1_234, $total->minorUnits)`.
- **Keep a currency of each precision** in the cases that compute: `JPY` (0), `CAD` (2), `BHD` (3). A computation
  that assumes 2 decimals fails on the yen.
- **Assert that the parts of a split add up** to the whole: `Money::sum(...$parts)->equals($amount)`.
- `Money` has no clock, container or I/O: no double is needed.
