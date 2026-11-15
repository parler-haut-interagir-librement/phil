<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;

/**
 * The options of what a reference sells, from the first `SellableOptions` that supports its type: the empty set when
 * none does.
 */
final readonly class LineOptions
{
    /**
     * @param iterable<SellableOptions> $catalogues
     */
    public function __construct(
        private iterable $catalogues = [],
    ) {
    }

    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        foreach ($this->catalogues as $catalogue) {
            if ($catalogue->supports($reference->type)) {
                return $catalogue->optionsOf($reference->unconfigured(), $currency);
            }
        }

        return OptionSet::none();
    }
}
