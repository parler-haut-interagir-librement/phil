<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

/**
 * How the customer's arrival was signalled: by the application on its own — a location the customer shares —, or by
 * the customer pressing "I am here" on the order, which works without any permission.
 */
enum ArrivalSource: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
