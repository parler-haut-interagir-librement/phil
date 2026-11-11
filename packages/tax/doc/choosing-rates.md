# Choosing the rates

The library computes and itemises; it never decides which rates apply. These stay with the application, or with
the external tax engine it delegates to:

- whether a seller must collect tax at all (registration, small-supplier thresholds);
- which jurisdiction taxes a sale (place of supply, the buyer's location for digital sales);
- the current rates, which change by law;
- the legal mentions of a receipt (registration numbers).

## Rates kept by the application

An application that keeps its rates in configuration reads them as percentages, the way tax authorities publish
them, and hands the calculator the list that applies:

```php
use Phil\Tax\TaxRate;

final readonly class ConfiguredTaxRates
{
    /** @param array<string, array<string, array<string, string>>> $rates category => zone => code => percent */
    public function __construct(private array $rates)
    {
    }

    /** @return list<TaxRate> */
    public function ratesFor(string $taxCategory, string $zone): array
    {
        $rates = [];

        foreach ($this->rates[$taxCategory][$zone] ?? [] as $code => $percent) {
            $rates[] = TaxRate::ofPercent($code, $percent);
        }

        return $rates;
    }
}
```

Return each code once: `TaxBreakdown` refuses a code that appears twice.

## Rates computed by an engine

An adapter over an external tax engine builds the breakdown from the engine's own amounts with
`TaxLine::reported()` and `TaxBreakdown::of()`, so that the receipt shows what the engine computed, to the cent.
