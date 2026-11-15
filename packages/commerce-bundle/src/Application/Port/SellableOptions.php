<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;

/**
 * Tells the core the options of what a reference sells: a catalogue declares, per product, its texts, choices, inks
 * and draws, with their supplements in the order's currency.
 *
 * A service implementing it is tagged `phil_commerce.sellable_options` by autoconfiguration.
 */
interface SellableOptions
{
    /**
     * Whether it declares the options of this type of reference; at most one should.
     */
    public function supports(string $type): bool;

    /**
     * The options of the sellable, the reference given without a line's configuration; the empty set for none.
     */
    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet;
}
