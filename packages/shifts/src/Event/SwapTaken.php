<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A volunteer took an offer of the swap board that waits for a coordinator's approval.
 */
final readonly class SwapTaken implements ShiftEvent
{
    public function __construct(
        public string $offerId,
        public string $shiftId,
        public string $volunteerId,
    ) {
    }
}
