# Exceptions

Every exception of the library implements `Phil\Tax\Exception\TaxException`, which extends `Throwable`: an
application maps them in one place. Each one is a mistake of the caller or of its input, never a failure of the
library. Two currencies meeting keep the type of `phil/money`, `Phil\Money\Exception\CurrencyMismatch`, which
implements `MoneyException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidTaxRate` | `InvalidArgumentException` | `TaxRate::of()` or `ofPercent()` receives a code, a share or a percentage a rate cannot hold |
| `InvalidBreakdown` | `DomainException` | `TaxBreakdown::of()` receives a line on another base, or a rate code twice |
| `UntaxableBase` | `DomainException` | `TaxRate::taxOn()` (and so `calculate()`) receives a base whose tax a native integer cannot hold, or `MultiRateTaxCalculator::extract()` receives a price larger than `intdiv(PHP_INT_MAX - parts, 1000000)` minor units, `parts` being one million plus the parts per million of every rate |

| Named constructor | Message |
|---|---|
| `InvalidTaxRate::code(string $code)` | `"gst" is not a tax rate code: up to 32 uppercase letters, digits, "_" or "-", starting with a letter.` |
| `InvalidTaxRate::outOfRange(int $partsPerMillion)` | `A tax rate lies between 0 and 1000000 parts per million; 1000001 does not.` |
| `InvalidTaxRate::notAPercent(string $percent)` | `"9,975" is not a percentage a tax rate holds: digits with up to 4 decimals after ".", from 0 to 100, are expected.` |
| `InvalidBreakdown::anotherBase(string $code)` | `The QST line is on another base: every rate of a breakdown applies to the same base.` |
| `InvalidBreakdown::duplicateRate(string $code)` | `The GST rate appears twice in one breakdown.` |
| `UntaxableBase::tooLarge(int $largest)` | `The taxable base is too large to be taxed in a native integer: 9223372036853 minor units at most.` |

The messages are written for developers and logs, not for the people who buy.
