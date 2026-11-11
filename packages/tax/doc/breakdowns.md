# Breakdowns

A sale may be taxed by several taxes at once, each on the same base, each rounded on its own line and itemised on
the invoice. A single-rate model returns one rate per sale and cannot express this.

| Type | Role |
|---|---|
| `TaxLine` | One line: a rate, its base, its amount |
| `TaxBreakdown` | Several lines on the same base; totals and reversal |
| `MultiRateTaxCalculator` | Applies every rate to the base and returns the breakdown, or takes the taxes out of a price that includes them |

## Example

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;
use Phil\Tax\TaxRate;

$base = Money::of(1_999, Currency::of('CAD')); // 19.99

$breakdown = new MultiRateTaxCalculator()->calculate($base, [
    TaxRate::of('GST', 50_000), // 5 %
    TaxRate::of('QST', 99_750), // 9.975 %
]);

$breakdown->lines[0]->amount->minorUnits;    // 100  (99.95 rounded)
$breakdown->line('QST')?->amount->minorUnits; // 199  (199.40 rounded), on 19.99, not on 19.99 + GST
$breakdown->totalTax()->minorUnits;          // 299
$breakdown->totalWithTax()->minorUnits;      // 2298

$credit = $breakdown->reversed();            // base -1999, lines -100 and -199
```

## `TaxLine`

| Member | Behaviour |
|---|---|
| `static computed(TaxRate $rate, Money $base): self` | Amount computed by `$rate->taxOn($base)` |
| `static reported(TaxRate $rate, Money $base, Money $amount): self` | Amount as an external tax engine reported it, kept as given; `CurrencyMismatch` when amount and base differ in currency |
| `public TaxRate $rate`, `public Money $base`, `public Money $amount` | The value |
| `reversed(): self` | Same rate, base and amount negated |

`reported()` exists for applications that delegate taxation to an external engine: the library then only itemises
what the engine computed, and does not recompute it.

## `TaxBreakdown`

| Member | Behaviour |
|---|---|
| `static of(Money $base, list<TaxLine> $lines): self` | Checks that every line is on exactly this base and that a rate code appears at most once |
| `public Money $base`, `public list<TaxLine> $lines` | The value, lines in the given order |
| `line(string $code): ?TaxLine` | The line of a rate code, or `null` |
| `totalTax(): Money` | Sum of the line amounts; zero in the base's currency when there is no line |
| `totalWithTax(): Money` | Base plus total tax |
| `reversed(): self` | The breakdown of a credit note: base and every line negated |

Refusals of `of()`:

| Case | Exception |
|---|---|
| A line's base is in another currency | `Phil\Money\Exception\CurrencyMismatch` |
| A line's base is another amount | `InvalidBreakdown`: every rate of a breakdown applies to the same base |
| A rate code appears twice | `InvalidBreakdown` |

- **Every rate applies to the same base, never to the base plus another tax.** Compounding is a different rule
  and would need a different type.
- **Each line is rounded on its own**, as the receipt shows it, never the sum of the rates once: two rates of 5 %
  on 0.10 give 1 cent each, 2 cents in all, where rounding the sum once would give 1.
- **A credit note reverses the itemised taxes** with `reversed()` rather than editing the invoice.

## `MultiRateTaxCalculator`

```php
final readonly class MultiRateTaxCalculator
{
    /** @param list<TaxRate> $rates */
    public function calculate(Money $base, array $rates): TaxBreakdown;

    /** @param list<TaxRate> $rates */
    public function extract(Money $price, array $rates): TaxBreakdown;
}
```

`calculate()` gives one computed line per rate, in the order given; no rate gives an empty breakdown. `extract()`
takes the taxes out of a price that includes them, as the next section shows. Stateless, no constructor argument.

## Prices that include their taxes

Where the price on the tag is the price paid, `extract()` takes the taxes out of it instead of adding them:

```php
$breakdown = new MultiRateTaxCalculator()->extract(Money::of(1_000, Currency::of('CAD')), [
    TaxRate::of('GST', 50_000),
    TaxRate::of('QST', 99_750),
]);

$breakdown->lines[0]->amount->minorUnits;  // 44   (5 % of 8.70)
$breakdown->lines[1]->amount->minorUnits;  // 87   (9.975 % of 8.70)
$breakdown->base->minorUnits;              // 869  (10.00 less the taxes)
$breakdown->totalWithTax()->minorUnits;    // 1000, the price, always
```

- The base before taxes is the price divided by one plus every rate, rounded half away from zero: 8.70 here.
- Each tax is computed on it and rounded on its own line, as `calculate()` does.
- The breakdown's base is the price less those taxes, so a rounding cent stays in the base and the price paid never
  changes: 8.69 here. Its lines are `reported()` on that base.
- A negative price gives exactly the negated taxes, for a refund.
- A price too large for the computation throws `UntaxableBase`: its magnitude may not exceed
  `intdiv(PHP_INT_MAX - parts, 1000000)` minor units, where `parts` is one million plus the parts per million of
  every rate.

