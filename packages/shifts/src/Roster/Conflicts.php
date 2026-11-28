<?php

declare(strict_types=1);

namespace Phil\Shifts\Roster;

use Phil\Shifts\Shift\Shift;

/**
 * The shifts a volunteer already holds that a new one would overlap: two fixed shifts whose windows meet, or come
 * closer than the rest asked between them — 0 to 1,440 minutes.
 */
final readonly class Conflicts
{
    public int $restMinutes;

    public function __construct(int $restMinutes = 0)
    {
        $this->restMinutes = max(0, min($restMinutes, Shift::MAX_MINUTES));
    }

    /**
     * @param iterable<Shift> $held the shifts the volunteer holds
     *
     * @return list<Shift> those the candidate overlaps, the candidate itself left out
     */
    public function of(Shift $candidate, iterable $held): array
    {
        $conflicts = [];

        foreach ($held as $shift) {
            if ($shift->id !== $candidate->id && $candidate->overlaps($shift, $this->restMinutes)) {
                $conflicts[] = $shift;
            }
        }

        return $conflicts;
    }
}
