<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A volunteer left a shift: a place, the reserve, or the list of the interested.
 */
final readonly class VolunteerWithdrew implements ShiftEvent
{
    public function __construct(
        public string $shiftId,
        public string $volunteerId,
    ) {
    }
}
