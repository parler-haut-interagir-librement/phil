<?php

declare(strict_types=1);

namespace Phil\Habits\Contract;

use Phil\Habits\CheckIn;
use Phil\Habits\Day;

/**
 * Where check-ins are kept, at most one per habit and day.
 */
interface CheckInStore
{
    /**
     * The check-in of a habit on a day, if there is one.
     */
    public function get(string $habit, Day $day): ?CheckIn;

    /**
     * The check-ins of a habit from a day to another, both included, the earliest first.
     *
     * @return list<CheckIn>
     */
    public function between(string $habit, Day $from, Day $through): array;

    /**
     * Keeps a check-in, in place of the one of its habit on its day if there is one.
     */
    public function save(CheckIn $checkIn): void;

    /**
     * Forgets the check-in of a habit on a day; nothing happens when there is none.
     */
    public function remove(string $habit, Day $day): void;

    /**
     * Forgets every check-in of a habit.
     */
    public function removeHabit(string $habit): void;
}
