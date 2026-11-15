<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\Tax\TaxRate;

/**
 * Says which rates apply to a sale.
 *
 * Registration thresholds, place of supply and the rules of each jurisdiction are the application's decision,
 * and often the job of an external tax engine. The adapter belongs to the application: rates configured by
 * hand, or the rates the engine returns, which `phil/tax` then only itemises.
 */
interface TaxRateResolver
{
    /**
     * @param string $taxCategory the category of what is sold, as the application names it
     * @param string $zone        the taxing zone of the buyer, as the application names it
     *
     * @return list<TaxRate> every rate that applies, each to the same base; an empty list when none does
     */
    public function ratesFor(string $taxCategory, string $zone): array;
}
