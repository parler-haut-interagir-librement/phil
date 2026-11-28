<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * What changed on a roster, by identifiers only: the shift and the volunteer. A listener reads the rest from the
 * store, so that no name, address or note travels in a queue.
 */
interface ShiftEvent
{
    public string $shiftId { get; }

    public string $volunteerId { get; }
}
