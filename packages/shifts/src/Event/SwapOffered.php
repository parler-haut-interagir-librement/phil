<?php

declare(strict_types=1);

namespace Phil\Shifts\Event;

/**
 * A volunteer put their place on the swap board.
 */
final readonly class SwapOffered implements ShiftEvent
{
    public function __construct(
        public string $offerId,
        public string $shiftId,
        public string $volunteerId,
    ) {
    }
}
