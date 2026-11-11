# Currencies

`Phil\Money\Currency` is an ISO 4217 alphabetic code that the Unicode CLDR data of `symfony/intl` knows.

```php
Currency::of('CAD');   // ok
Currency::of('XYZ');   // UnknownCurrency: the shape of a code, but no currency
Currency::of('cad');   // UnknownCurrency: codes are case-sensitive
```

**Known, not merely well formed.** A code that names nothing has no precision, name or symbol; refusing it at
the edge keeps every amount meaningful. A lowercase code is refused rather than corrected: it is the sign of input
that was never validated. Which currencies an application *accepts* is a narrower decision, made by its
configuration (`phil_money.currencies` with the bundle).

| Member | Contract |
|---|---|
| `static of(string $code): self` | The currency, or `UnknownCurrency`; the same instance for the same code |
| `public string $code` | The three-letter code |
| `fractionDigits(): int` | Decimals an amount counts: 2 for `CAD`, 0 for `JPY`, 3 for `BHD`, 4 for `CLF` |
| `name(?string $locale = null): string` | `dollar canadien` in `fr`, `Canadian Dollar` in `en`; the default locale when none is given |
| `symbol(?string $locale = null): string` | `$` in `fr_CA`, `CA$` in `en` |
| `equals(self $other): bool` | Same code |
| `__toString(): string` | The code |

**One instance per code.** The first `of()` of a code looks it up in the CLDR data, which costs several
microseconds; every later call returns the same instance from a per-process map. An entity that keeps a code and
rebuilds its currency on each read pays an array access, not a lookup. Only known codes enter the map, so it never
holds more than the few hundred codes the CLDR knows. Compare currencies with `equals()` all the same: the shared
instance is a saving, not a contract to compare by identity.

The fraction digits are the CLDR's, which is what a person expects to read and type; a payment processor may
count some currencies differently (the Icelandic króna, for instance), and its adapter converts.
