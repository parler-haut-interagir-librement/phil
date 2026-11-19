<?php

declare(strict_types=1);

namespace Phil\Habits\Memory;

use Override;
use Phil\Habits\Contract\HabitStore;
use Phil\Habits\Habit;

/**
 * The habits in an array, for tests and for a single process. They are lost with the object.
 */
final class InMemoryHabitStore implements HabitStore
{
    /** @var array<string, Habit> */
    private array $habits = [];

    #[Override]
    public function get(string $id): ?Habit
    {
        return $this->habits[$id] ?? null;
    }

    #[Override]
    public function ofMember(string $member): array
    {
        $habits = array_filter($this->habits, static fn (Habit $habit): bool => $habit->member === $member);
        ksort($habits, \SORT_STRING);

        return array_values($habits);
    }

    #[Override]
    public function save(Habit $habit): void
    {
        $this->habits[$habit->id] = $habit;
    }

    #[Override]
    public function remove(string $id): void
    {
        unset($this->habits[$id]);
    }
}
