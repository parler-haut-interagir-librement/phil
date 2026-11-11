# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. It computes and itemises;
which rates apply is the application's, and reaches the library as arguments.

| Point | How |
|---|---|
| Which rates apply | The list given to `MultiRateTaxCalculator::calculate()` or `extract()`: a resolver of the application's own, from configuration or an external tax engine, builds it with `TaxRate::of()` or `TaxRate::ofPercent()` — see [Choosing the rates](choosing-rates.md) |
| Taxes an engine already computed | `TaxLine::reported()` keeps an amount as it was given; lines are gathered by `TaxBreakdown::of()`: the breakdown checks they share one base and keeps them without recomputing |
| Credit notes | `TaxBreakdown::reversed()` negates every line exactly, whatever produced it |
| Catching the library's errors | Every exception implements `Phil\Tax\Exception\TaxException`: one `catch` covers `InvalidTaxRate`, `InvalidBreakdown` and `UntaxableBase` |

The rounding — half away from zero, per line — is the library's contract and is not replaced: a jurisdiction that
rounds otherwise reports its lines computed elsewhere.
