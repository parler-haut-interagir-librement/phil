# Exceptions

Every exception of the library implements `Phil\Money\Exception\MoneyException`, which extends `Throwable`:
an application maps them in one place. Each one is a mistake of the caller or of its input, never a failure of the
library.

| Exception | Extends | Thrown when |
|---|---|---|
| `UnknownCurrency` | `InvalidArgumentException` | `Currency::of()` receives a code the CLDR data does not know: `"XYZ" is not a known ISO 4217 currency code.` |
| `InvalidAmount` | `InvalidArgumentException` | A decimal that is not one, or finer than the currency; an amount outside the native integer; a refused allocation |
| `CurrencyMismatch` | `DomainException` | Two currencies meet in `add()`, `subtract()`, `compare()`, `isGreaterThan()`, `isLessThan()` or `sum()`: `An amount in CAD cannot be combined with an amount in USD.` |

The messages of `InvalidAmount`:

| Named constructor | Message |
|---|---|
| `notADecimal(string $amount)` | `"12,34" is not a decimal amount: digits, with an optional "-" and "." are expected.` |
| `tooPrecise(string $amount, string $currency, int $fractionDigits)` | `"12.345" has more decimals than CAD counts (2).` |
| `outOfRange()` | `The amount leaves the range of a native integer.` |
| `because(string $reason)` | The reason given: the refusals of `allocate()` and `split()` |

The messages are written for developers and logs. A person entering an amount sees the form's own message, which
the bundle translates.
