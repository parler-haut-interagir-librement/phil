<?php

declare(strict_types=1);

namespace Phil\Shifts\Hours;

use DateTimeImmutable;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;

/**
 * The minutes a volunteer served: the shifts whose window is over and on which they held a place, each for its minutes
 * and for its weighted minutes — what a certificate of volunteer hours or a reward by thresholds reads.
 */
final readonly class ServedHours
{
    /**
     * @param list<string> $shifts the shifts counted, in the order of the rosters
     */
    public function __construct(
        public string $volunteer,
        public int $minutes,
        public int $weightedMinutes,
        public array $shifts,
    ) {
    }

    /**
     * @param iterable<Roster> $rosters
     */
    public static function of(string $volunteer, iterable $rosters, DateTimeImmutable $now): self
    {
        $minutes = 0;
        $weighted = 0;
        $shifts = [];

        foreach ($rosters as $roster) {
            if ($roster->shift->isOverAt($now) && PlaceStatus::Confirmed === $roster->statusOf($volunteer)) {
                $minutes += $roster->shift->minutes;
                $weighted += $roster->shift->weightedMinutes();
                $shifts[] = $roster->shift->id;
            }
        }

        return new self($volunteer, $minutes, $weighted, $shifts);
    }

    /**
     * The minutes the volunteer holds, served or to serve, on the rosters given: what an allocator balances.
     *
     * @param iterable<Roster> $rosters
     *
     * @return array<string, int> the minutes by volunteer
     */
    public static function held(iterable $rosters): array
    {
        $held = [];

        foreach ($rosters as $roster) {
            foreach ($roster->confirmed() as $place) {
                $held[$place->volunteer] = ($held[$place->volunteer] ?? 0) + $roster->shift->minutes;
            }
        }

        return $held;
    }
}
