<?php

declare(strict_types=1);

namespace Phil\Tax;

use Phil\Money\Money;
use Phil\Tax\Exception\UntaxableBase;

/**
 * Applies every given rate to the same base and returns one line per rate.
 *
 * A single-rate resolver returns one rate per sale; a sale taxed by a federal and a provincial tax needs both,
 * each computed on the base and each rounded on its own line, as the receipt shows them.
 *
 * A price that already includes its taxes goes through `extract()` instead: the taxes are taken out of it, and the
 * base is what is left.
 */
final readonly class MultiRateTaxCalculator
{
    /**
     * @param list<TaxRate> $rates
     */
    public function calculate(Money $base, array $rates): TaxBreakdown
    {
        return TaxBreakdown::of($base, array_map(static fn (TaxRate $rate): TaxLine => TaxLine::computed($rate, $base), $rates));
    }

    /**
     * The taxes included in a price, and the base they leave: `totalWithTax()` gives the price back, to the cent.
     *
     * The base before taxes is the price divided by one plus every rate, rounded half away from zero; each tax is
     * then computed on it and rounded on its own line, as `calculate()` does. The base of the breakdown is the
     * price less those taxes, so that a rounding cent stays in the base and never changes the price paid: 10.00
     * with GST at 5 % and QST at 9.975 % gives GST 0.44 and QST 0.87, computed on 8.70, and a base of 8.69.
     *
     * @param list<TaxRate> $rates
     */
    public function extract(Money $price, array $rates): TaxBreakdown
    {
        $parts = TaxRate::ONE_MILLION;

        foreach ($rates as $rate) {
            $parts += $rate->partsPerMillion;
        }

        $magnitude = abs($price->minorUnits);
        $largest = intdiv(\PHP_INT_MAX - $parts, TaxRate::ONE_MILLION);

        if ($magnitude > $largest) {
            throw UntaxableBase::tooLarge($largest);
        }

        $net = intdiv($magnitude * TaxRate::ONE_MILLION + intdiv($parts, 2), $parts);
        $before = Money::of($price->isNegative() ? -$net : $net, $price->currency);
        $taxes = array_map(static fn (TaxRate $rate): array => [$rate, $rate->taxOn($before)], $rates);
        $base = $price;

        foreach ($taxes as [, $amount]) {
            $base = $base->subtract($amount);
        }

        return TaxBreakdown::of($base, array_map(static fn (array $tax): TaxLine => TaxLine::reported($tax[0], $base, $tax[1]), $taxes));
    }
}
