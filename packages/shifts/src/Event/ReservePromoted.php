<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * The first volunteer of the reserve took the place another one freed.
 */
final readonly class ReservePromoted implements ShiftEvent
{
    public function __construct(
        public string $shiftId,
        public string $volunteerId,
    ) {
    }
}
