<?php

declare(strict_types=1);

namespace Phil\Habits\Memory;

use Override;
use Phil\Habits\CheckIn;
use Phil\Habits\Contract\CheckInStore;
use Phil\Habits\Day;

/**
 * The check-ins in an array, for tests and for a single process. They are lost with the object.
 */
final class InMemoryCheckInStore implements CheckInStore
{
    /** @var array<string, array<int, CheckIn>> habit → day → check-in */
    private array $checkIns = [];

    #[Override]
    public function get(string $habit, Day $day): ?CheckIn
    {
        return $this->checkIns[$habit][$day->number] ?? null;
    }

    #[Override]
    public function between(string $habit, Day $from, Day $through): array
    {
        $checkIns = array_filter(
            $this->checkIns[$habit] ?? [],
            static fn (int $day): bool => $day >= $from->number && $day <= $through->number,
            \ARRAY_FILTER_USE_KEY,
        );
        ksort($checkIns);

        return array_values($checkIns);
    }

    #[Override]
    public function save(CheckIn $checkIn): void
    {
        $this->checkIns[$checkIn->habit][$checkIn->day->number] = $checkIn;
    }

    #[Override]
    public function remove(string $habit, Day $day): void
    {
        unset($this->checkIns[$habit][$day->number]);
    }

    #[Override]
    public function removeHabit(string $habit): void
    {
        unset($this->checkIns[$habit]);
    }
}
