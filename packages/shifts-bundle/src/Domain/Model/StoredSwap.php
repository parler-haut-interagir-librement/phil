<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Model;

use Phil\Shifts\Swap\SwapOffer;

/**
 * An offer of the swap board as its storage keeps it, with the revision a write compares.
 */
final class StoredSwap
{
    public function __construct(
        public readonly SwapOffer $offer,
        public readonly int $revision,
    ) {
    }
}
