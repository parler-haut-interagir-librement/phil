<?php

declare(strict_types=1);

namespace Phil\Habits\Contract;

/**
 * How a habit's strength moves with each period: the strength before it, how much of the period was done, and the
 * number of days the period stands for. A strength runs from 0 to 1.
 */
interface StrengthModel
{
    /**
     * @param float $strength   the strength before the period, from 0 to 1
     * @param float $completion how much of the period was done, from 0 to 1
     * @param float $days       the number of days the period stands for, more than 0
     *
     * @return float the strength after the period, from 0 to 1
     */
    public function next(float $strength, float $completion, float $days): float;
}
