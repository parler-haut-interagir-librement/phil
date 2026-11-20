<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Quantity;

/**
 * How a quantity breaks its rule: under the minimum, over the maximum, or between two steps of the increment.
 */
enum QuantityBreach: string
{
    case BelowMinimum = 'below_minimum';
    case AboveMaximum = 'above_maximum';
    case OffIncrement = 'off_increment';
}
