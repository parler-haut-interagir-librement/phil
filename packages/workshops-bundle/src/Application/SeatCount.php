<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Application;

/**
 * A session's seats at a moment: how many it holds, how many confirmed registrations take, how many are free, how many
 * of those its waiting list claims — offered, or asked for by those waiting —, and how many a new registration takes.
 */
final readonly class SeatCount
{
    public int $free;

    public int $available;

    public function __construct(
        public int $capacity,
        public int $taken,
        public int $claimed = 0,
    ) {
        $this->free = max(0, $capacity - $taken);
        $this->available = max(0, $this->free - $claimed);
    }

    /**
     * Whether a new registration finds no seat: every seat taken, or claimed by the waiting list.
     */
    public function isFull(): bool
    {
        return 0 === $this->available;
    }
}
