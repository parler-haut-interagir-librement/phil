<?php

declare(strict_types=1);

namespace Phil\Habits;

use Phil\Habits\Contract\StrengthModel;

/**
 * Works out a habit's streaks and strength from its check-ins, period by period, from its first day to a day — today,
 * or its last day when it was archived earlier.
 *
 * Each period over is met, excused or missed. It is met when its check-ins bring the units it asks; excused when its
 * skipped and frozen days, counted as done, would have; missed otherwise. The period that holds the last day is met
 * as soon as it is, and waits otherwise. A met period adds one to the streak and moves the strength towards 1; a
 * missed one brings the streak back to zero and moves the strength towards the share done; an excused or waiting one
 * changes neither.
 */
final readonly class ProgressCalculator
{
    public function __construct(
        private StrengthModel $strength = new HalfLifeStrength(),
    ) {
    }

    /**
     * @param iterable<CheckIn> $checkIns the habit's check-ins, in any order; those of other days than its periods'
     *                                    are left out
     */
    public function calculate(Habit $habit, iterable $checkIns, Day $today): Progress
    {
        $through = $habit->archivedOn instanceof Day && $habit->archivedOn->isBefore($today) ? $habit->archivedOn : $today;
        $byDay = [];

        foreach ($checkIns as $checkIn) {
            $byDay[$checkIn->day->number] = $checkIn;
        }

        $unitsPerDay = $habit->unitsPerDay();
        $current = 0;
        $best = 0;
        $strength = 0.0;
        $last = null;

        foreach ($habit->schedule->periods($habit->startsOn, $through) as $period) {
            $last = $this->progressOf($period, $byDay, $unitsPerDay);

            if ($last->isMet()) {
                $best = max($best, ++$current);
                $strength = $this->strength->next($strength, 1.0, $period->span);

                continue;
            }

            if ($period->holds($through) || $last->done + $last->excusedDays * $unitsPerDay >= $last->required) {
                continue;
            }

            $current = 0;
            $strength = $this->strength->next($strength, $last->completion(), $period->span);
        }

        return new Progress(new Streak($current, $best, $habit->schedule->streakUnit()), $strength, $last);
    }

    /**
     * @param array<int, CheckIn> $byDay
     */
    private function progressOf(Period $period, array $byDay, int $unitsPerDay): PeriodProgress
    {
        $done = 0;
        $excused = 0;

        for ($day = $period->first->number; $day <= $period->last->number; ++$day) {
            $checkIn = $byDay[$day] ?? null;

            if (null === $checkIn) {
                continue;
            }

            $done += $checkIn->units($unitsPerDay);
            $excused += $checkIn->status->isExcused() ? 1 : 0;
        }

        return new PeriodProgress($period, $done, $period->required * $unitsPerDay, $excused);
    }
}
