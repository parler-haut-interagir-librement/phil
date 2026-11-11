# Reference

Every type of the library, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Tax\TaxRate` | `final readonly class` | A code and a share in parts per million; percentages; the tax on a base |
| `Phil\Tax\TaxLine` | `final readonly class` | A rate, its base, its amount; computed or reported |
| `Phil\Tax\TaxBreakdown` | `final readonly class` | Lines on one base; line by code; totals; reversal |
| `Phil\Tax\MultiRateTaxCalculator` | `final readonly class` | Every rate on the same base, one line each (`calculate()`); the taxes included in a price, and the base they leave (`extract()`) |
| `Phil\Tax\Exception\TaxException` | interface, extends `Throwable` | Every exception of the library |
| `Phil\Tax\Exception\InvalidTaxRate` | `final class`, `InvalidArgumentException` | A code, share or percentage refused |
| `Phil\Tax\Exception\InvalidBreakdown` | `final class`, `DomainException` | A line on another base, or a code twice |
| `Phil\Tax\Exception\UntaxableBase` | `final class`, `DomainException` | A base too large to tax in a native integer |

See [rates.md](rates.md), [breakdowns.md](breakdowns.md), [choosing-rates.md](choosing-rates.md) and
[exceptions.md](exceptions.md).
