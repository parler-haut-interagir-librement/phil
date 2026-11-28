<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

/**
 * Where a pickup point stands: a store's counter, or the booth a seller holds at a convention for its days.
 */
enum PickupPointKind: string
{
    case Store = 'store';
    case ConventionBooth = 'convention_booth';
}
