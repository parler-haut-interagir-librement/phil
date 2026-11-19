<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Layaway;

enum LayawayState: string
{
    /** Goods put aside, paid in instalments. */
    case Open = 'open';

    /** Paid in full: the goods go home. */
    case Completed = 'completed';

    /** Given up: the goods go back on the shelf. */
    case Cancelled = 'cancelled';
}
