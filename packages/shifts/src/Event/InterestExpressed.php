<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A volunteer is interested in a shift whose places are allocated.
 */
final readonly class InterestExpressed implements ShiftEvent
{
    public function __construct(
        public string $shiftId,
        public string $volunteerId,
    ) {
    }
}
