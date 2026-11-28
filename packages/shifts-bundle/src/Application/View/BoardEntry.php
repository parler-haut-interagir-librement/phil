<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\View;

use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Swap\SwapOffer;

/**
 * An offer of the swap board with the roster of its shift, as a page shows it.
 */
final readonly class BoardEntry
{
    public function __construct(
        public SwapOffer $offer,
        public Roster $roster,
    ) {
    }
}
