<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\TaxRateResolver;
use Phil\Tax\TaxRate;

/**
 * The rates of `phil_commerce.tax_rates`: for each tax category, for each zone, the rates as their percentages.
 * The resolver the bundle registers unless the application aliases its own to `TaxRateResolver`.
 */
final readonly class ConfiguredTaxRates implements TaxRateResolver
{
    /** @var array<string, array<string, list<TaxRate>>> */
    private array $rates;

    /**
     * @param array<string, array<string, array<string, string>>> $percentages category => zone => code => percent
     */
    public function __construct(array $percentages)
    {
        $rates = [];

        foreach ($percentages as $category => $zones) {
            foreach ($zones as $zone => $codes) {
                $rates[$category][$zone] = [];

                foreach ($codes as $code => $percent) {
                    $rates[$category][$zone][] = TaxRate::ofPercent($code, $percent);
                }
            }
        }

        $this->rates = $rates;
    }

    #[Override]
    public function ratesFor(string $taxCategory, string $zone): array
    {
        return $this->rates[$taxCategory][$zone] ?? [];
    }
}
