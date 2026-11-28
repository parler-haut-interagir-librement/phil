<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A place passed to another volunteer through the swap board: the volunteer who took it, and the one who gave it.
 */
final readonly class PlaceTransferred implements ShiftEvent
{
    public function __construct(
        public string $shiftId,
        public string $volunteerId,
        public string $fromVolunteerId,
    ) {
    }
}
