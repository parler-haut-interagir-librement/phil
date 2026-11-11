<?php

declare(strict_types=1);

namespace Phil\Reservation\Availability;

use Override;
use Phil\Reservation\Contract\Availability;
use Phil\Reservation\Interval;

/**
 * A resource open at every moment: only its rules and its bookings limit it.
 */
final readonly class AlwaysAvailable implements Availability
{
    #[Override]
    public function windows(Interval $period): array
    {
        return [$period];
    }
}
