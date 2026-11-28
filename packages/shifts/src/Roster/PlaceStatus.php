<?php

declare(strict_types=1);

namespace Phil\Shifts\Roster;

/**
 * Where a volunteer stands on a roster: holding a place, waiting in the reserve, or interested in a shift whose places
 * are allocated.
 */
enum PlaceStatus: string
{
    case Confirmed = 'confirmed';
    case Reserve = 'reserve';
    case Interested = 'interested';
}
