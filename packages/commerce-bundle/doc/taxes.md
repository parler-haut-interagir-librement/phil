# Taxes

Rates, lines, breakdowns and the multi-rate calculator are the types of `phil/tax`, which the bundle requires:
rates in parts per million, each tax on the same base, rounded half away from zero on its own line, and
breakdowns that a credit note reverses exactly. That library's documentation describes them. The bundle adds
the port that says which rates apply to a sale, the rates it reads from its configuration, and the processor that
taxes each line of an order.

| Type | Package | Role |
|---|---|---|
| `Phil\CommerceBundle\Application\Port\TaxRateResolver` | this bundle | Port: which rates apply to a sale |
| `Phil\CommerceBundle\Application\ConfiguredTaxRates` | this bundle | The rates of `phil_commerce.tax_rates`; the resolver registered by default |
| `Phil\CommerceBundle\Application\TaxOrderProcessor` | this bundle | The order processor that taxes each line |
| `Phil\Tax\TaxRate` | `phil/tax` | A code and a share in parts per million; the tax on a base |
| `Phil\Tax\TaxBreakdown`, `Phil\Tax\TaxLine` | `phil/tax` | Lines on one base, totals, reversal |
| `Phil\Tax\MultiRateTaxCalculator` | `phil/tax` | Every rate on the same base, one line each |

## Example

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;

$rates = $resolver->ratesFor('digital', 'QC');  // GST 5 % and QST 9.975 %, from the application

$breakdown = new MultiRateTaxCalculator()->calculate(Money::of(1_999, Currency::of('CAD')), $rates);
$breakdown->totalTax()->minorUnits;              // 299: 100 and 199, each on 19.99
$credit = $breakdown->reversed();                // what a credit note carries
```

## Taxing an order

`TaxOrderProcessor` runs with every change an `OrderModifier` makes:

- each line is taxed on its subtotal **with what its options add**, the `Option` adjustments on that line
  ([options.md](options.md)), **after its own promotions**, the `Promotion` adjustments on that line, and **after
  its proration**, the `Proration` adjustments a subscription module puts on that line; promotions on the whole
  order, fees and shipping are not part of a line's base;
- the rates are those `TaxRateResolver` gives for the line's tax category in the order's tax zone;
- one `Tax` adjustment per line and per rate, labelled with the rate's code;
- an order without a tax zone carries no tax adjustment;
- running it again replaces its adjustments, so it never adds a tax twice.

## Prices that include the taxes

An order whose `taxIncluded()` is true — set by `OrderModifier::changeTaxIncluded()`, or by the register for a
session sold that way — is sold at the prices on the tags. The processor then takes the taxes out of each line with
`MultiRateTaxCalculator::extract()` instead of adding them, and marks its adjustments **neutral**: shown on the
receipt, left out of the total.

```php
$orders->changeTaxIncluded($order, true);
$order->total();        // 10.00, the price on the tag
$order->includedTax();  // 1.31: GST 0.44 and QST 0.87, taken out of it
```

The same unit prices serve both ways: switching the order recomputes its taxes and nothing else.

A promotion processor must run before it: its tag names it, `['before' => TaxOrderProcessor::class]`.

## `TaxRateResolver`

Registration thresholds, place of supply and the rules of each jurisdiction are the application's decision,
often the job of an external tax engine.

```php
interface TaxRateResolver
{
    /** @return list<Phil\Tax\TaxRate> every rate that applies, each to the same base; an empty list when none does */
    public function ratesFor(string $taxCategory, string $zone): array;
}
```

Both arguments are names the application chooses. `ConfiguredTaxRates` answers from `phil_commerce.tax_rates`
([configuration.md](configuration.md)); an application with an external engine aliases its own adapter to the
port, whose contract is in [extension-points.md](extension-points.md).

## What the bundle does not decide

- Whether a seller must collect tax at all (registration, small-supplier thresholds).
- Which jurisdiction taxes a sale (place of supply, the buyer's location for digital sales).
- The legal mentions of a receipt (registration numbers).
- The current rates: rates change by law, and the application or its tax engine holds them.
