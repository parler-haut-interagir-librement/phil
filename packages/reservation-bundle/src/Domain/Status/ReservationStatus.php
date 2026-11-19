<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Status;

/**
 * Where a reservation stands. A hold keeps its units until it expires; a confirmed reservation keeps them until it
 * is cancelled. The values are stored.
 */
enum ReservationStatus: string
{
    case Held = 'held';

    case Confirmed = 'confirmed';

    case Cancelled = 'cancelled';

    case Expired = 'expired';
}
