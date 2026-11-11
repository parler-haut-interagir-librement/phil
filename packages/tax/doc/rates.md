# Rates

A `TaxRate` is a code and a share of the base, in parts per million.

| Member | Behaviour |
|---|---|
| `static of(string $code, int $partsPerMillion): self` | Code: 1 to 32 characters, uppercase letters, digits, `_` or `-`, starting with a letter. Share: 0 to 1 000 000 ppm. Otherwise `InvalidTaxRate` |
| `static ofPercent(string $code, string $percent): self` | The share from its percentage as a decimal string, `"9.975"`: digits, up to four decimals after `.`, from 0 to 100. Otherwise `InvalidTaxRate` |
| `public string $code`, `public int $partsPerMillion` | The value |
| `const ONE_MILLION = 1_000_000` | 100 % |
| `percent(): string` | The percentage without trailing zeros: `"9.975"`, `"5"`, `"0.0001"` |
| `taxOn(Money $base): Money` | The tax on the base, in the base's currency, rounded half away from zero to the minor unit |
| `equals(self $other): bool` | Same code and same share |

```php
use Phil\Tax\TaxRate;

TaxRate::of('QST', 99_750)->percent();               // "9.975"
TaxRate::ofPercent('QST', '9.975')->partsPerMillion; // 99750
TaxRate::ofPercent('HST', '13.00')->percent();        // "13"
```

## Parts per million

Not a float and not basis points. 9.975 % has three decimals in percent: basis points cannot hold it, a float
cannot hold it exactly, `99_750` ppm can. Four decimals in percent is the precision of a part per million, so
`ofPercent()` refuses a fifth rather than rounding it.

## The code

What a receipt shows and what a credit note reverses (`GST`, `QST`, `VAT`…). Codes are compared exactly, case
included; a breakdown holds each code once.

## Rounding

**Half away from zero, on the minor unit of the base's currency.** `0.5` becomes `1`, `-0.5` becomes `-1`. The tax
on a negated base is therefore exactly the negated tax, which is what a credit note relies on. The computation is
integer only: `(|base| × ppm + 500 000) ÷ 1 000 000`, then the sign of the base.

The currency's precision is the base's own: 10 % of 1 000 yen is 100 yen, 10 % of 12.500 dinars is 1.250 dinars.

## The largest base

`|base| × ppm + 500 000` must fit a native integer at any share, so a base whose absolute value exceeds
`intdiv(PHP_INT_MAX - 1_000_000, 1_000_000)` minor units — about 92 billion in a two-decimal currency, on a 64-bit
platform — is refused with `UntaxableBase`. Anything up to it is taxed exactly.
