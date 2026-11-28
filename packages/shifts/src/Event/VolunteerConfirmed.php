<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A volunteer holds a place: signed up, assigned by a coordinator, or allocated among the interested. The coordinator who assigned it, when one did.
 */
final readonly class VolunteerConfirmed implements ShiftEvent
{
    public function __construct(
        public string $shiftId,
        public string $volunteerId,
        public ?string $assignedBy = null,
    ) {
    }
}
