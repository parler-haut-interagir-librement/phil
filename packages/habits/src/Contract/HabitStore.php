<?php

declare(strict_types=1);

namespace Phil\Habits\Contract;

use Phil\Habits\Habit;

/**
 * Where habits are kept.
 */
interface HabitStore
{
    public function get(string $id): ?Habit;

    /**
     * The habits of a member, archived ones included, by identifier: their part of an access or erasure request.
     *
     * @return list<Habit>
     */
    public function ofMember(string $member): array;

    /**
     * Keeps a new habit, or the new state of one.
     */
    public function save(Habit $habit): void;

    /**
     * Forgets a habit; nothing happens for one the store does not have.
     */
    public function remove(string $id): void;
}
