<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A volunteer waits in the reserve of a full shift, and takes the first place that frees.
 */
final readonly class VolunteerReserved implements ShiftEvent
{
    public function __construct(
        public string $shiftId,
        public string $volunteerId,
    ) {
    }
}
