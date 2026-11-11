# Amounts

`Phil\Money\Money` is an amount in the smallest unit of its currency — cents for a dollar — with that currency.

## Creating an amount

| Constructor | Contract |
|---|---|
| `static of(int $minorUnits, Currency $currency): self` | Any integer but `PHP_INT_MIN` |
| `static zero(Currency $currency): self` | Zero in that currency |
| `static ofDecimal(string $amount, Currency $currency): self` | A decimal written by a person or a file |
| `static sum(self $first, self ...$others): self` | The sum of one amount or more, all in one currency |

`ofDecimal()` reads digits, an optional leading `-` and an optional `.` followed by digits:

```php
$cad = Currency::of('CAD');

Money::ofDecimal('12.34', $cad);   // 1234
Money::ofDecimal('12.5', $cad);    // 1250
Money::ofDecimal('12.3400', $cad); // 1234: trailing zeros are not precision
Money::ofDecimal('12.345', $cad);  // InvalidAmount: finer than the currency counts
Money::ofDecimal('12,34', $cad);   // InvalidAmount: not a decimal
Money::ofDecimal('1200', Currency::of('JPY')); // 1200
```

A decimal finer than the currency is **refused, never rounded**: rounding is a business decision (a price, a tax,
an interest) that belongs to the code that makes it. Grouping separators, a locale's decimal comma and spaces are
the reader's job; the bundle's form type reads them in the request's locale.

## Reading an amount

| Member | Contract |
|---|---|
| `public int $minorUnits`, `public Currency $currency` | The value |
| `toDecimal(): string` | `12.34`, `-0.05`, `0.00`, `1200` for the yen: always every decimal of the currency |
| `isZero()`, `isPositive()`, `isNegative()` | The sign |

`toDecimal()` is a machine format, the inverse of `ofDecimal()`. Displaying an amount to a person — symbol,
grouping, the locale's decimal separator — is `NumberFormatter`'s job, or the bundle's `format_money` filter.

## Arithmetic and comparison

| Member | Contract |
|---|---|
| `add(self $other)`, `subtract(self $other)` | Same currency only |
| `negate()`, `abs()` | Always possible: the range is symmetric |
| `multiply(int $factor)` | A quantity, a number of months; a fraction is `allocate()`'s job |
| `compare(self $other): int` | `-1`, `0` or `1`; same currency only |
| `isGreaterThan(self $other)`, `isLessThan(self $other)` | Same currency only |
| `equals(self $other): bool` | Same currency and amount; `false`, never an exception, across currencies |

Every operation returns a new amount: `Money` is immutable.

## Design decisions

- **Integers in minor units.** A float cannot hold most decimal amounts exactly, and its errors add up across lines.
- **The currency is part of the value.** Amounts in two currencies are never added, subtracted or compared: the
  operation throws `CurrencyMismatch`. **No conversion happens here, ever.**
- **A negative amount is legitimate.** A credit note reverses an invoice line.
- **The range is the native integer, symmetric.** PHP turns an integer overflow into a float silently; `Money`
  refuses it with `InvalidAmount`. `PHP_INT_MIN` is refused too, since it has no positive counterpart: every amount
  can be negated. On 64-bit PHP, that is over 92 trillion dollars.
